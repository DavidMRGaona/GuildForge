<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class CoreCheckUpdatesCommandTest extends TestCase
{
    private MockInterface&CoreVersionServiceInterface $versionService;

    private MockInterface&CoreUpdateCheckerInterface $updateChecker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionService = Mockery::mock(CoreVersionServiceInterface::class);
        $this->updateChecker = Mockery::mock(CoreUpdateCheckerInterface::class);

        $this->app->instance(CoreVersionServiceInterface::class, $this->versionService);
        $this->app->instance(CoreUpdateCheckerInterface::class, $this->updateChecker);
    }

    public function test_it_displays_current_version(): void
    {
        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn(ModuleVersion::fromString('1.0.0'));

        $this->updateChecker->shouldReceive('checkForUpdates')
            ->andReturn(null);

        $this->artisan('core:check-updates')
            ->expectsOutput('Current core version: v1.0.0')
            ->assertExitCode(0);
    }

    public function test_it_shows_message_when_no_update_available(): void
    {
        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn(ModuleVersion::fromString('2.0.0'));

        $this->updateChecker->shouldReceive('checkForUpdates')
            ->andReturn(null);

        $this->artisan('core:check-updates')
            ->expectsOutput('You are running the latest version.')
            ->assertExitCode(0);
    }

    public function test_it_displays_available_update_when_found(): void
    {
        $currentVersion = ModuleVersion::fromString('1.0.0');
        $newVersion = ModuleVersion::fromString('1.2.0');

        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn($currentVersion);

        $release = new GitHubReleaseInfo(
            tagName: 'v1.2.0',
            version: $newVersion,
            downloadUrl: 'https://example.com/download.zip',
            checksumUrl: '',
            releaseNotes: 'Bug fixes and improvements',
            publishedAt: new DateTimeImmutable('2024-08-15 10:30:00'),
            isPrerelease: false,
        );

        $this->updateChecker->shouldReceive('checkForUpdates')
            ->andReturn($release);

        $this->artisan('core:check-updates')
            ->expectsOutput('Update available!')
            ->assertExitCode(0);
    }

    public function test_it_displays_update_table_with_version_info(): void
    {
        $currentVersion = ModuleVersion::fromString('1.0.0');
        $newVersion = ModuleVersion::fromString('2.0.0');

        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn($currentVersion);

        $release = new GitHubReleaseInfo(
            tagName: 'v2.0.0',
            version: $newVersion,
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable('2024-08-15 10:30:00'),
            isPrerelease: false,
        );

        $this->updateChecker->shouldReceive('checkForUpdates')
            ->andReturn($release);

        $this->artisan('core:check-updates')
            ->expectsTable(
                ['Field', 'Value'],
                [
                    ['Current version', 'v1.0.0'],
                    ['Available version', 'v2.0.0'],
                    ['Published', '2024-08-15 10:30'],
                    ['Pre-release', 'No'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_it_displays_prerelease_status_when_applicable(): void
    {
        $currentVersion = ModuleVersion::fromString('1.0.0');
        $newVersion = ModuleVersion::fromString('2.0.0');

        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn($currentVersion);

        $release = new GitHubReleaseInfo(
            tagName: 'v2.0.0-beta.1',
            version: $newVersion,
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable('2024-08-15 10:30:00'),
            isPrerelease: true,
        );

        $this->updateChecker->shouldReceive('checkForUpdates')
            ->andReturn($release);

        $this->artisan('core:check-updates')
            ->expectsTable(
                ['Field', 'Value'],
                [
                    ['Current version', 'v1.0.0'],
                    ['Available version', 'v2.0.0'],
                    ['Published', '2024-08-15 10:30'],
                    ['Pre-release', 'Yes'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_it_displays_release_notes_when_available(): void
    {
        $currentVersion = ModuleVersion::fromString('1.0.0');
        $newVersion = ModuleVersion::fromString('1.1.0');

        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn($currentVersion);

        $release = new GitHubReleaseInfo(
            tagName: 'v1.1.0',
            version: $newVersion,
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: 'Added new feature X',
            publishedAt: new DateTimeImmutable('2024-08-15 10:30:00'),
            isPrerelease: false,
        );

        $this->updateChecker->shouldReceive('checkForUpdates')
            ->andReturn($release);

        $this->artisan('core:check-updates')
            ->expectsOutput('Release notes:')
            ->expectsOutput('Added new feature X')
            ->assertExitCode(0);
    }

    public function test_it_returns_success_exit_code(): void
    {
        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn(ModuleVersion::fromString('1.0.0'));

        $this->updateChecker->shouldReceive('checkForUpdates')
            ->andReturn(null);

        $this->artisan('core:check-updates')
            ->assertExitCode(0);
    }
}
