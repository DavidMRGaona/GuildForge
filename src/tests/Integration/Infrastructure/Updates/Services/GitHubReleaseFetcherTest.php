<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use App\Infrastructure\Updates\Services\GitHubReleaseFetcher;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GitHubReleaseFetcherTest extends TestCase
{
    private GitHubReleaseFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fetcher = new GitHubReleaseFetcher();
        Cache::flush();
    }

    public function test_it_fetches_latest_release_from_github(): void
    {
        Http::fake([
            'api.github.com/repos/test-owner/test-repo/releases/latest' => Http::response([
                'tag_name' => 'v1.2.0',
                'body' => 'Release notes here',
                'published_at' => '2024-08-15T10:30:00Z',
                'prerelease' => false,
                'assets' => [
                    [
                        'name' => 'module-1.2.0.zip',
                        'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/v1.2.0/module-1.2.0.zip',
                    ],
                    [
                        'name' => 'module-1.2.0.zip.sha256',
                        'browser_download_url' => 'https://github.com/test-owner/test-repo/releases/download/v1.2.0/module-1.2.0.zip.sha256',
                    ],
                ],
            ]),
        ]);

        $release = $this->fetcher->getLatestRelease('test-owner', 'test-repo');

        $this->assertInstanceOf(GitHubReleaseInfo::class, $release);
        $this->assertEquals('v1.2.0', $release->tagName);
        $this->assertEquals('1.2.0', $release->version->value());
        $this->assertEquals('Release notes here', $release->releaseNotes);
        $this->assertFalse($release->isPrerelease);
        $this->assertTrue($release->hasDownloadableAssets());
        $this->assertTrue($release->hasChecksum());
    }

    public function test_it_caches_results_for_configured_ttl(): void
    {
        Http::fake([
            'api.github.com/repos/cache-owner/cache-repo/releases/latest' => Http::sequence()
                ->push([
                    'tag_name' => 'v1.0.0',
                    'body' => 'First call',
                    'published_at' => '2024-01-01T00:00:00Z',
                    'prerelease' => false,
                    'assets' => [],
                ])
                ->push([
                    'tag_name' => 'v2.0.0',
                    'body' => 'Second call - should not be reached',
                    'published_at' => '2024-01-01T00:00:00Z',
                    'prerelease' => false,
                    'assets' => [],
                ]),
        ]);

        $firstResult = $this->fetcher->getLatestRelease('cache-owner', 'cache-repo');
        $secondResult = $this->fetcher->getLatestRelease('cache-owner', 'cache-repo');

        $this->assertEquals('v1.0.0', $firstResult->tagName);
        $this->assertEquals('v1.0.0', $secondResult->tagName);

        // Should only make one HTTP request
        Http::assertSentCount(1);
    }

    public function test_it_returns_null_when_no_releases(): void
    {
        Http::fake([
            'api.github.com/repos/empty-owner/empty-repo/releases/latest' => Http::response([], 404),
        ]);

        $release = $this->fetcher->getLatestRelease('empty-owner', 'empty-repo');

        $this->assertNull($release);
    }

    public function test_it_batch_fetches_multiple_repos(): void
    {
        Http::fake([
            'api.github.com/repos/owner1/repo1/releases/latest' => Http::response([
                'tag_name' => 'v1.0.0',
                'body' => '',
                'published_at' => '2024-01-01T00:00:00Z',
                'prerelease' => false,
                'assets' => [],
            ]),
            'api.github.com/repos/owner2/repo2/releases/latest' => Http::response([
                'tag_name' => 'v2.0.0',
                'body' => '',
                'published_at' => '2024-01-01T00:00:00Z',
                'prerelease' => false,
                'assets' => [],
            ]),
            'api.github.com/repos/owner3/repo3/releases/latest' => Http::response([], 404),
        ]);

        $repos = [
            ['owner' => 'owner1', 'repo' => 'repo1'],
            ['owner' => 'owner2', 'repo' => 'repo2'],
            ['owner' => 'owner3', 'repo' => 'repo3'],
        ];

        $results = $this->fetcher->batchFetchLatestReleases($repos);

        $this->assertCount(3, $results);
        $this->assertInstanceOf(GitHubReleaseInfo::class, $results['owner1/repo1']);
        $this->assertInstanceOf(GitHubReleaseInfo::class, $results['owner2/repo2']);
        $this->assertNull($results['owner3/repo3']);
        $this->assertEquals('v1.0.0', $results['owner1/repo1']->tagName);
        $this->assertEquals('v2.0.0', $results['owner2/repo2']->tagName);
    }

    public function test_it_verifies_checksum_correctly(): void
    {
        $fileContent = 'test file content for checksum verification';
        $expectedChecksum = hash('sha256', $fileContent);

        Http::fake([
            'example.com/checksum.sha256' => Http::response("{$expectedChecksum}  module.zip"),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $fileContent);

        try {
            $release = new GitHubReleaseInfo(
                tagName: 'v1.0.0',
                version: ModuleVersion::fromString('1.0.0'),
                downloadUrl: 'https://example.com/module.zip',
                checksumUrl: 'https://example.com/checksum.sha256',
                releaseNotes: '',
                publishedAt: new DateTimeImmutable(),
                isPrerelease: false,
            );

            $result = $this->fetcher->fetchAndVerifyChecksum($release, $tempFile);

            $this->assertTrue($result);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_it_throws_exception_on_checksum_mismatch(): void
    {
        $fileContent = 'test file content';
        $wrongChecksum = 'wrongchecksum1234567890abcdef1234567890abcdef1234567890abcdef1234';

        Http::fake([
            'example.com/checksum.sha256' => Http::response("{$wrongChecksum}  module.zip"),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $fileContent);

        try {
            $release = new GitHubReleaseInfo(
                tagName: 'v1.0.0',
                version: ModuleVersion::fromString('1.0.0'),
                downloadUrl: 'https://example.com/module.zip',
                checksumUrl: 'https://example.com/checksum.sha256',
                releaseNotes: '',
                publishedAt: new DateTimeImmutable(),
                isPrerelease: false,
            );

            $this->expectException(UpdateException::class);
            $this->expectExceptionMessage('Checksum verification failed');

            $this->fetcher->fetchAndVerifyChecksum($release, $tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_it_handles_rate_limit_errors(): void
    {
        Http::fake([
            'api.github.com/repos/rate-limited/repo/releases/latest' => Http::response([
                'message' => 'API rate limit exceeded',
            ], 403),
        ]);

        $release = $this->fetcher->getLatestRelease('rate-limited', 'repo');

        $this->assertNull($release);
    }

    public function test_it_skips_verification_when_no_checksum_available(): void
    {
        $release = new GitHubReleaseInfo(
            tagName: 'v1.0.0',
            version: ModuleVersion::fromString('1.0.0'),
            downloadUrl: 'https://example.com/module.zip',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'any content');

        try {
            $result = $this->fetcher->fetchAndVerifyChecksum($release, $tempFile);

            $this->assertTrue($result);
            Http::assertNothingSent();
        } finally {
            unlink($tempFile);
        }
    }

    public function test_it_clears_cache_for_specific_repo(): void
    {
        Http::fake([
            'api.github.com/repos/clear-cache/repo/releases/latest' => Http::sequence()
                ->push([
                    'tag_name' => 'v1.0.0',
                    'body' => '',
                    'published_at' => '2024-01-01T00:00:00Z',
                    'prerelease' => false,
                    'assets' => [],
                ])
                ->push([
                    'tag_name' => 'v2.0.0',
                    'body' => '',
                    'published_at' => '2024-01-01T00:00:00Z',
                    'prerelease' => false,
                    'assets' => [],
                ]),
        ]);

        $firstResult = $this->fetcher->getLatestRelease('clear-cache', 'repo');
        $this->assertEquals('v1.0.0', $firstResult->tagName);

        $this->fetcher->clearCache('clear-cache', 'repo');

        $secondResult = $this->fetcher->getLatestRelease('clear-cache', 'repo');
        $this->assertEquals('v2.0.0', $secondResult->tagName);
    }

    public function test_it_uses_cached_results_in_batch_fetch(): void
    {
        // First, cache a release for owner1/repo1
        Http::fake([
            'api.github.com/repos/cached-owner/cached-repo/releases/latest' => Http::response([
                'tag_name' => 'v1.0.0',
                'body' => 'Cached',
                'published_at' => '2024-01-01T00:00:00Z',
                'prerelease' => false,
                'assets' => [],
            ]),
        ]);

        $this->fetcher->getLatestRelease('cached-owner', 'cached-repo');

        // Now batch fetch, should use cached result
        Http::fake([
            'api.github.com/repos/new-owner/new-repo/releases/latest' => Http::response([
                'tag_name' => 'v2.0.0',
                'body' => 'New',
                'published_at' => '2024-01-01T00:00:00Z',
                'prerelease' => false,
                'assets' => [],
            ]),
        ]);

        $repos = [
            ['owner' => 'cached-owner', 'repo' => 'cached-repo'],
            ['owner' => 'new-owner', 'repo' => 'new-repo'],
        ];

        $results = $this->fetcher->batchFetchLatestReleases($repos);

        $this->assertEquals('v1.0.0', $results['cached-owner/cached-repo']->tagName);
        $this->assertEquals('v2.0.0', $results['new-owner/new-repo']->tagName);

        // Should only fetch the non-cached repo
        Http::assertSentCount(1);
    }

    public function test_service_is_registered_in_container(): void
    {
        $service = $this->app->make(GitHubReleaseFetcherInterface::class);

        $this->assertInstanceOf(GitHubReleaseFetcher::class, $service);
    }
}
