<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Modules\Services\HostEnvironmentProviderInterface;
use App\Application\Modules\Services\ModuleCompatibilityChecker;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\Services\ConstraintMatcher;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\HostEnvironment;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use App\Domain\Updates\ValueObjects\ReleaseSelection;
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

        $this->fetcher = new GitHubReleaseFetcher(new ModuleCompatibilityChecker(new ConstraintMatcher()), $this->hostAt('2.6.0'));
        Cache::flush();
    }

    public function test_it_selects_the_latest_release_from_github(): void
    {
        Http::fake([
            'api.github.com/repos/test-owner/test-repo/releases*' => Http::response([
                $this->release('v1.2.0', body: 'Release notes here'),
            ]),
        ]);

        $release = $this->latest('test-owner', 'test-repo');

        $this->assertInstanceOf(GitHubReleaseInfo::class, $release);
        $this->assertEquals('v1.2.0', $release->tagName);
        $this->assertEquals('1.2.0', $release->version->value());
        $this->assertEquals('Release notes here', $release->releaseNotes);
        $this->assertFalse($release->isPrerelease);
        $this->assertTrue($release->hasDownloadableAssets());
        $this->assertTrue($release->hasChecksum());
    }

    public function test_it_compares_a_commit_with_a_branch_newest_first(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/compare/aaa...main' => Http::response([
                'status' => 'ahead',
                'ahead_by' => 2,
                'behind_by' => 0,
                'commits' => [
                    $this->commit('bbb', "fix: one\n\nLonger body", '2026-10-04T10:00:00Z'),
                    $this->commit('ccc', 'feat: two', '2026-10-04T11:00:00Z'),
                ],
            ]),
        ]);

        $comparison = $this->fetcher->compareCommits('o', 'r', 'aaa', 'main');

        $this->assertSame(2, $comparison->aheadBy);
        $this->assertSame(0, $comparison->behindBy);
        $this->assertSame('ccc', $comparison->headSha);
        $this->assertSame(['ccc', 'bbb'], array_column($comparison->commits, 'sha'));
        $this->assertSame('fix: one', $comparison->commits[1]['message']);
        $this->assertSame('https://github.com/o/r/commit/bbb', $comparison->commits[1]['url']);
        $this->assertSame('2026-10-04T10:00:00Z', $comparison->commits[1]['date']);
    }

    public function test_comparing_an_identical_commit_reports_nothing_pending(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/compare/aaa...main' => Http::response([
                'status' => 'identical', 'ahead_by' => 0, 'behind_by' => 0, 'commits' => [],
            ]),
        ]);

        $comparison = $this->fetcher->compareCommits('o', 'r', 'aaa', 'main');

        $this->assertSame(0, $comparison->aheadBy);
        $this->assertSame('aaa', $comparison->headSha);
        $this->assertSame([], $comparison->commits);
    }

    public function test_comparing_throws_when_github_fails(): void
    {
        Http::fake(['api.github.com/repos/o/r/compare/*' => Http::response(['message' => 'Not Found'], 404)]);

        $this->expectException(UpdateException::class);

        $this->fetcher->compareCommits('o', 'r', 'aaa', 'main');
    }

    public function test_it_returns_highest_prerelease_when_prereleases_included(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([
                $this->release('v1.0.9-beta', prerelease: true),
                $this->release('v1.0.10-beta', prerelease: true),
                $this->release('v1.1.0', draft: true),
            ]),
        ]);

        $release = $this->latest('o', 'r', includePrereleases: true);

        $this->assertSame('1.0.10-beta', $release?->version->value());
    }

    public function test_it_skips_prereleases_by_default(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([
                $this->release('v2.0.0-beta', prerelease: true),
                $this->release('v1.5.0'),
            ]),
        ]);

        $this->assertSame('1.5.0', $this->latest('o', 'r')?->version->value());
    }

    public function test_it_ignores_tags_that_are_not_versions(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([
                $this->release('nightly'),
                $this->release('v1.0.0'),
            ]),
        ]);

        $this->assertSame('1.0.0', $this->latest('o', 'r')?->version->value());
    }

    public function test_it_caches_results_for_configured_ttl(): void
    {
        Http::fake([
            'api.github.com/repos/cache-owner/cache-repo/releases*' => Http::sequence()
                ->push([$this->release('v1.0.0')])
                ->push([$this->release('v2.0.0')]),
        ]);

        $firstResult = $this->latest('cache-owner', 'cache-repo');
        $secondResult = $this->latest('cache-owner', 'cache-repo');

        $this->assertEquals('v1.0.0', $firstResult?->tagName);
        $this->assertEquals('v1.0.0', $secondResult?->tagName);
        Http::assertSentCount(1);
    }

    public function test_it_returns_null_when_repo_has_no_releases(): void
    {
        Http::fake([
            'api.github.com/repos/empty-owner/empty-repo/releases*' => Http::response([]),
        ]);

        $this->assertNull($this->latest('empty-owner', 'empty-repo'));
    }

    public function test_it_throws_when_repo_is_missing_or_private(): void
    {
        Http::fake([
            'api.github.com/repos/o/private/releases*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('o/private');

        $this->latest('o', 'private');
    }

    public function test_it_throws_on_api_error_and_does_not_cache_it(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::sequence()
                ->push(['message' => 'API rate limit exceeded'], 403)
                ->push([$this->release('v1.0.0')]),
        ]);

        try {
            $this->latest('o', 'r');
            $this->fail('Expected UpdateException');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('403', $e->getMessage());
        }

        $this->assertSame('1.0.0', $this->latest('o', 'r')?->version->value());
    }

    public function test_batch_returns_exception_for_failing_repo_and_selection_for_others(): void
    {
        Http::fake([
            'api.github.com/repos/o/ok/releases*' => Http::response([$this->release('v1.0.0')]),
            'api.github.com/repos/o/private/releases*' => Http::response(['message' => 'Not Found'], 404),
            'api.github.com/repos/o/empty/releases*' => Http::response([]),
        ]);
        $installed = new ModuleVersion(0, 1, 0);

        $results = $this->fetcher->batchSelectReleases([
            ['owner' => 'o', 'repo' => 'ok', 'installed' => $installed],
            ['owner' => 'o', 'repo' => 'private', 'installed' => $installed],
            ['owner' => 'o', 'repo' => 'empty', 'installed' => $installed],
        ]);

        $this->assertInstanceOf(ReleaseSelection::class, $results['o/ok']);
        $this->assertSame('1.0.0', $results['o/ok']->compatible?->version->value());
        $this->assertInstanceOf(UpdateException::class, $results['o/private']);
        $this->assertInstanceOf(ReleaseSelection::class, $results['o/empty']);
        $this->assertNull($results['o/empty']->compatible);
    }

    public function test_batch_includes_prereleases_when_requested(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([$this->release('v1.0.9-beta', prerelease: true)]),
        ]);

        $results = $this->fetcher->batchSelectReleases([['owner' => 'o', 'repo' => 'r', 'installed' => ModuleVersion::fromString('1.0.8-beta')]], true);

        $this->assertInstanceOf(ReleaseSelection::class, $results['o/r']);
        $this->assertSame('1.0.9-beta', $results['o/r']->compatible?->version->value());
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
                publishedAt: new DateTimeImmutable,
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
                publishedAt: new DateTimeImmutable,
                isPrerelease: false,
            );

            $this->expectException(UpdateException::class);
            $this->expectExceptionMessage('Checksum verification failed');

            $this->fetcher->fetchAndVerifyChecksum($release, $tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_it_skips_verification_when_no_checksum_available(): void
    {
        $release = new GitHubReleaseInfo(
            tagName: 'v1.0.0',
            version: ModuleVersion::fromString('1.0.0'),
            downloadUrl: 'https://example.com/module.zip',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable,
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
            'api.github.com/repos/clear-cache/repo/releases*' => Http::sequence()
                ->push([$this->release('v1.0.0')])
                ->push([$this->release('v2.0.0')]),
        ]);

        $this->assertEquals('v1.0.0', $this->latest('clear-cache', 'repo')?->tagName);

        $this->fetcher->clearCache('clear-cache', 'repo');

        $this->assertEquals('v2.0.0', $this->latest('clear-cache', 'repo')?->tagName);
    }

    public function test_clear_cache_does_not_flush_other_cache_entries(): void
    {
        Cache::put('unrelated', 'keep');
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::sequence()
                ->push([$this->release('v1.0.0')])
                ->push([$this->release('v2.0.0')]),
        ]);
        $this->latest('o', 'r');

        $this->fetcher->clearCache();

        $this->assertSame('keep', Cache::get('unrelated'));
        $this->assertEquals('v2.0.0', $this->latest('o', 'r')?->tagName);
    }

    public function test_it_uses_cached_results_in_batch_select(): void
    {
        Http::fake([
            'api.github.com/repos/cached-owner/cached-repo/releases*' => Http::response([$this->release('v1.0.0')]),
            'api.github.com/repos/new-owner/new-repo/releases*' => Http::response([$this->release('v2.0.0')]),
        ]);
        $this->latest('cached-owner', 'cached-repo');
        $installed = new ModuleVersion(0, 1, 0);

        $results = $this->fetcher->batchSelectReleases([
            ['owner' => 'cached-owner', 'repo' => 'cached-repo', 'installed' => $installed],
            ['owner' => 'new-owner', 'repo' => 'new-repo', 'installed' => $installed],
        ]);

        $this->assertInstanceOf(ReleaseSelection::class, $results['cached-owner/cached-repo']);
        $this->assertInstanceOf(ReleaseSelection::class, $results['new-owner/new-repo']);
        $this->assertSame('v1.0.0', $results['cached-owner/cached-repo']->compatible?->tagName);
        $this->assertSame('v2.0.0', $results['new-owner/new-repo']->compatible?->tagName);
        Http::assertSentCount(2);
    }

    public function test_a_release_without_manifest_asset_defaults_to_core_2(): void
    {
        Http::fake(['api.github.com/repos/o/r/releases*' => Http::response([$this->release('v1.1.0')])]);

        $selection = $this->fetcher->selectRelease('o', 'r', ModuleVersion::fromString('1.0.7-beta'), false);

        $this->assertSame('1.1.0', $selection->compatible?->version->value());
        $this->assertSame('^2.0', $selection->compatibleCoreConstraint);
        $this->assertNull($selection->blocked);
        Http::assertSentCount(1);
    }

    public function test_it_selects_the_highest_compatible_release_and_the_highest_blocked_one(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([
                $this->release('v1.1.0', withManifest: true),
                $this->release('v2.0.0', withManifest: true),
                $this->release('v1.2.0', withManifest: true),
            ]),
            'example.test/v2.0.0/module.json' => Http::response(['name' => 'm', 'requires' => ['core' => '^3.0']]),
            'example.test/v1.2.0/module.json' => Http::response(['name' => 'm', 'requires' => ['core' => '^2.6']]),
            'example.test/v1.1.0/module.json' => Http::response(['name' => 'm', 'requires' => ['core' => '^2.6']]),
        ]);

        $selection = $this->fetcher->selectRelease('o', 'r', ModuleVersion::fromString('1.0.0'), false);

        $this->assertSame('1.2.0', $selection->compatible?->version->value());
        $this->assertSame('^2.6', $selection->compatibleCoreConstraint);
        $this->assertSame('2.0.0', $selection->blocked?->version->value());
        $this->assertSame('^3.0', $selection->blockedCoreConstraint);
        $this->assertEquals([new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED)], $selection->blockedIssues);
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), 'v1.1.0/module.json'));
    }

    public function test_only_a_blocked_release_selects_nothing_compatible(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([$this->release('v2.0.0', withManifest: true)]),
            'example.test/v2.0.0/module.json' => Http::response(['name' => 'm', 'requires' => ['core' => '^3.0', 'filament' => '^4.0']]),
        ]);

        $selection = $this->fetcher->selectRelease('o', 'r', ModuleVersion::fromString('1.1.0'), false);

        $this->assertNull($selection->compatible);
        $this->assertSame('2.0.0', $selection->blocked?->version->value());
        $this->assertCount(2, $selection->blockedIssues);
    }

    public function test_releases_not_newer_than_the_installed_one_are_ignored(): void
    {
        Http::fake(['api.github.com/repos/o/r/releases*' => Http::response([$this->release('v1.1.0'), $this->release('v1.0.0')])]);

        $selection = $this->fetcher->selectRelease('o', 'r', ModuleVersion::fromString('1.1.0'), false);

        $this->assertNull($selection->compatible);
        $this->assertNull($selection->blocked);
    }

    public function test_a_malformed_manifest_asset_blocks_the_release(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([$this->release('v1.1.0', withManifest: true), $this->release('v1.0.5')]),
            'example.test/v1.1.0/module.json' => Http::response('this is not json'),
        ]);

        $selection = $this->fetcher->selectRelease('o', 'r', ModuleVersion::fromString('1.0.0'), false);

        $this->assertSame('1.0.5', $selection->compatible?->version->value());
        $this->assertSame('1.1.0', $selection->blocked?->version->value());
        $this->assertNull($selection->blockedCoreConstraint);
        $this->assertEquals([new CompatibilityIssue(RequirementType::Manifest, '', null, CompatibilityIssue::MANIFEST_INVALID)], $selection->blockedIssues);
    }

    public function test_a_manifest_with_a_malformed_core_requirement_blocks_the_release(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([$this->release('v1.1.0', withManifest: true)]),
            'example.test/v1.1.0/module.json' => Http::response(['name' => 'm', 'requires' => ['core' => 3]]),
        ]);

        $selection = $this->fetcher->selectRelease('o', 'r', ModuleVersion::fromString('1.0.0'), false);

        $this->assertNull($selection->compatible);
        $this->assertSame(CompatibilityIssue::INVALID_CONSTRAINT, $selection->blockedIssues[0]->reasonKey);
    }

    public function test_an_oversized_manifest_asset_blocks_the_release(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([$this->release('v1.1.0', withManifest: true)]),
            'example.test/v1.1.0/module.json' => Http::response(json_encode(['name' => 'm', 'padding' => str_repeat('x', 70_000)])),
        ]);

        $selection = $this->fetcher->selectRelease('o', 'r', ModuleVersion::fromString('1.0.0'), false);

        $this->assertNull($selection->compatible);
        $this->assertEquals([new CompatibilityIssue(RequirementType::Manifest, '', null, CompatibilityIssue::MANIFEST_INVALID)], $selection->blockedIssues);
    }

    public function test_a_manifest_download_error_is_an_update_exception(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([$this->release('v1.1.0', withManifest: true)]),
            'example.test/v1.1.0/module.json' => Http::response('Bad gateway', 502),
        ]);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('o/r');

        $this->fetcher->selectRelease('o', 'r', ModuleVersion::fromString('1.0.0'), false);
    }

    public function test_manifests_are_cached_and_clear_cache_forgets_them(): void
    {
        Http::fake([
            'api.github.com/repos/o/r/releases*' => Http::response([$this->release('v1.1.0', withManifest: true)]),
            'example.test/v1.1.0/module.json' => Http::response(['name' => 'm', 'requires' => ['core' => '^2.6']]),
        ]);
        $installed = ModuleVersion::fromString('1.0.0');

        $this->fetcher->selectRelease('o', 'r', $installed, false);
        $this->fetcher->selectRelease('o', 'r', $installed, false);
        Http::assertSentCount(2);

        $this->fetcher->clearCache('o', 'r');
        $this->fetcher->selectRelease('o', 'r', $installed, false);
        Http::assertSentCount(4);
    }

    public function test_a_core_3_host_blocks_releases_that_require_core_2(): void
    {
        $fetcher = new GitHubReleaseFetcher(new ModuleCompatibilityChecker(new ConstraintMatcher()), $this->hostAt('3.0.0'));
        Http::fake(['api.github.com/repos/o/r/releases*' => Http::response([$this->release('v1.1.0')])]);

        $selection = $fetcher->selectRelease('o', 'r', ModuleVersion::fromString('1.0.0'), false);

        $this->assertNull($selection->compatible);
        $this->assertSame('^2.0', $selection->blockedCoreConstraint);
    }

    public function test_service_is_registered_in_container(): void
    {
        $service = $this->app->make(GitHubReleaseFetcherInterface::class);

        $this->assertInstanceOf(GitHubReleaseFetcher::class, $service);
    }

    /**
     * The highest compatible release for a module installed at 0.0.0, i.e. the latest usable release.
     */
    private function latest(string $owner, string $repo, bool $includePrereleases = false): ?GitHubReleaseInfo
    {
        return $this->fetcher->selectRelease($owner, $repo, new ModuleVersion(0, 0, 0), $includePrereleases)->compatible;
    }

    private function hostAt(string $core): HostEnvironmentProviderInterface
    {
        return new class ($core) implements HostEnvironmentProviderInterface {
            public function __construct(
                private readonly string $core,
            ) {
            }

            public function current(): HostEnvironment
            {
                return new HostEnvironment(
                    core: ModuleVersion::fromString($this->core),
                    php: new ModuleVersion(8, 4, 26),
                    laravel: new ModuleVersion(12, 69, 3),
                    filament: new ModuleVersion(3, 3, 56),
                    extensions: ['json'],
                );
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function release(string $tag, bool $prerelease = false, bool $draft = false, string $body = '', bool $withManifest = false): array
    {
        $version = ltrim($tag, 'v');
        $assets = [
            ['name' => "m-{$version}.zip", 'browser_download_url' => "https://example.test/m-{$version}.zip"],
            ['name' => "m-{$version}.zip.sha256", 'browser_download_url' => "https://example.test/m-{$version}.zip.sha256"],
        ];

        if ($withManifest) {
            $assets[] = ['name' => 'module.json', 'browser_download_url' => "https://example.test/{$tag}/module.json"];
        }

        return [
            'tag_name' => $tag,
            'prerelease' => $prerelease,
            'draft' => $draft,
            'body' => $body,
            'published_at' => '2026-10-04T10:00:00Z',
            'assets' => $assets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commit(string $sha, string $message, string $date): array
    {
        return [
            'sha' => $sha,
            'html_url' => "https://github.com/o/r/commit/{$sha}",
            'commit' => ['message' => $message, 'author' => ['date' => $date]],
        ];
    }
}
