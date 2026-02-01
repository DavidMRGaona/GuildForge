<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use App\Infrastructure\Updates\Services\CoreUpdateChecker;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class CoreUpdateCheckerTest extends TestCase
{
    private MockInterface&CoreVersionServiceInterface $versionService;

    private MockInterface&GitHubReleaseFetcherInterface $githubFetcher;

    private CoreUpdateChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionService = Mockery::mock(CoreVersionServiceInterface::class);
        $this->githubFetcher = Mockery::mock(GitHubReleaseFetcherInterface::class);

        $this->checker = new CoreUpdateChecker(
            $this->versionService,
            $this->githubFetcher,
        );
    }

    public function test_check_for_update_returns_release_when_newer_version_available(): void
    {
        config(['updates.core.owner' => 'guildforge', 'updates.core.repo' => 'core']);

        $currentVersion = ModuleVersion::fromString('1.0.0');
        $newVersion = ModuleVersion::fromString('1.1.0');

        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn($currentVersion);

        $release = new GitHubReleaseInfo(
            tagName: 'v1.1.0',
            version: $newVersion,
            downloadUrl: 'https://github.com/guildforge/core/releases/download/v1.1.0/core.zip',
            checksumUrl: '',
            releaseNotes: 'New features',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->with('guildforge', 'core')
            ->andReturn($release);

        $result = $this->checker->checkForUpdate();

        $this->assertInstanceOf(GitHubReleaseInfo::class, $result);
        $this->assertEquals('v1.1.0', $result->tagName);
    }

    public function test_check_for_update_returns_null_when_already_on_latest(): void
    {
        config(['updates.core.owner' => 'guildforge', 'updates.core.repo' => 'core']);

        $currentVersion = ModuleVersion::fromString('2.0.0');

        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn($currentVersion);

        $release = new GitHubReleaseInfo(
            tagName: 'v2.0.0',
            version: ModuleVersion::fromString('2.0.0'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->with('guildforge', 'core')
            ->andReturn($release);

        $result = $this->checker->checkForUpdate();

        $this->assertNull($result);
    }

    public function test_check_for_update_returns_null_when_no_release_found(): void
    {
        config(['updates.core.owner' => 'guildforge', 'updates.core.repo' => 'core']);

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->with('guildforge', 'core')
            ->andReturn(null);

        $result = $this->checker->checkForUpdate();

        $this->assertNull($result);
    }

    public function test_check_for_update_returns_null_when_no_core_config(): void
    {
        config(['updates.core.owner' => null, 'updates.core.repo' => null]);

        $result = $this->checker->checkForUpdate();

        $this->assertNull($result);
        $this->githubFetcher->shouldNotHaveReceived('getLatestRelease');
    }

    public function test_check_for_update_skips_prereleases_by_default(): void
    {
        config([
            'updates.core.owner' => 'guildforge',
            'updates.core.repo' => 'core',
            'updates.behavior.allow_prereleases' => false,
        ]);

        $currentVersion = ModuleVersion::fromString('1.0.0');

        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn($currentVersion);

        $release = new GitHubReleaseInfo(
            tagName: 'v2.0.0-beta.1',
            version: ModuleVersion::fromString('2.0.0'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: true,
        );

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->with('guildforge', 'core')
            ->andReturn($release);

        $result = $this->checker->checkForUpdate();

        $this->assertNull($result);
    }

    public function test_check_for_update_includes_prereleases_when_allowed(): void
    {
        config([
            'updates.core.owner' => 'guildforge',
            'updates.core.repo' => 'core',
            'updates.behavior.allow_prereleases' => true,
        ]);

        $currentVersion = ModuleVersion::fromString('1.0.0');

        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn($currentVersion);

        $release = new GitHubReleaseInfo(
            tagName: 'v2.0.0-beta.1',
            version: ModuleVersion::fromString('2.0.0'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: true,
        );

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->with('guildforge', 'core')
            ->andReturn($release);

        $result = $this->checker->checkForUpdate();

        $this->assertInstanceOf(GitHubReleaseInfo::class, $result);
    }

    public function test_is_major_upgrade_returns_true_for_major_version_change(): void
    {
        $currentVersion = ModuleVersion::fromString('1.5.3');

        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn($currentVersion);

        $release = new GitHubReleaseInfo(
            tagName: 'v2.0.0',
            version: ModuleVersion::fromString('2.0.0'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $result = $this->checker->isMajorUpgrade($release);

        $this->assertTrue($result);
    }

    public function test_is_major_upgrade_returns_false_for_minor_version_change(): void
    {
        $currentVersion = ModuleVersion::fromString('1.5.3');

        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn($currentVersion);

        $release = new GitHubReleaseInfo(
            tagName: 'v1.6.0',
            version: ModuleVersion::fromString('1.6.0'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $result = $this->checker->isMajorUpgrade($release);

        $this->assertFalse($result);
    }

    public function test_get_update_instructions_generates_correct_markdown(): void
    {
        $this->versionService->shouldReceive('getCurrentCommit')
            ->andReturn('abc123def456');

        $release = new GitHubReleaseInfo(
            tagName: 'v2.0.0',
            version: ModuleVersion::fromString('2.0.0'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: 'Major improvements and bug fixes',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $instructions = $this->checker->getUpdateInstructions($release);

        $this->assertStringContainsString('v2.0.0', $instructions);
        $this->assertStringContainsString('abc123def456', $instructions);
        $this->assertStringContainsString('Major improvements and bug fixes', $instructions);
        $this->assertStringContainsString('git checkout tags/v2.0.0', $instructions);
        $this->assertStringContainsString('composer install', $instructions);
        $this->assertStringContainsString('php artisan migrate', $instructions);
    }

    public function test_service_is_registered_in_container(): void
    {
        $service = $this->app->make(CoreUpdateCheckerInterface::class);

        $this->assertInstanceOf(CoreUpdateChecker::class, $service);
    }
}
