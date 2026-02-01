<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Application\Updates\DTOs\AvailableUpdateDTO;
use App\Application\Updates\DTOs\ModuleUpdateResultDTO;
use App\Application\Updates\DTOs\UpdatePreviewDTO;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Domain\Updates\Exceptions\UpdateException;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class UpdateModuleCommandTest extends TestCase
{
    private MockInterface&ModuleUpdateCheckerInterface $updateChecker;

    private MockInterface&ModuleUpdaterInterface $updater;

    protected function setUp(): void
    {
        parent::setUp();

        $this->updateChecker = Mockery::mock(ModuleUpdateCheckerInterface::class);
        $this->updater = Mockery::mock(ModuleUpdaterInterface::class);

        $this->app->instance(ModuleUpdateCheckerInterface::class, $this->updateChecker);
        $this->app->instance(ModuleUpdaterInterface::class, $this->updater);
    }

    public function test_it_requires_module_name_or_all_flag(): void
    {
        $this->artisan('module:update')
            ->expectsOutput('Please specify a module name or use --all')
            ->assertExitCode(1);
    }

    public function test_it_shows_preview_before_update(): void
    {
        $preview = new UpdatePreviewDTO(
            moduleName: 'forum',
            fromVersion: '1.0.0',
            toVersion: '1.2.0',
            pendingMigrations: ['create_posts_table.php'],
            newSeeders: [],
            changelog: 'Bug fixes',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: '^1.0',
            downloadUrl: 'https://example.com/forum.zip',
            downloadSize: null,
        );

        $this->updater->shouldReceive('preview')
            ->with(Mockery::on(fn ($arg) => $arg instanceof ModuleName && $arg->value === 'forum'))
            ->andReturn($preview);

        $this->artisan('module:update', ['name' => 'forum', '--dry-run' => true])
            ->expectsOutput('Module: forum')
            ->expectsOutput('Current version: 1.0.0')
            ->expectsOutput('Available version: 1.2.0')
            ->expectsOutput('Pending migrations: 1')
            ->expectsOutput('Dry run - no changes made.')
            ->assertExitCode(0);
    }

    public function test_dry_run_does_not_apply_changes(): void
    {
        $preview = new UpdatePreviewDTO(
            moduleName: 'shop',
            fromVersion: '2.0.0',
            toVersion: '2.1.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $this->updater->shouldReceive('preview')
            ->andReturn($preview);

        $this->updater->shouldNotReceive('update');

        $this->artisan('module:update', ['name' => 'shop', '--dry-run' => true])
            ->expectsOutput('Dry run - no changes made.')
            ->assertExitCode(0);
    }

    public function test_force_skips_confirmation(): void
    {
        $preview = new UpdatePreviewDTO(
            moduleName: 'gallery',
            fromVersion: '1.0.0',
            toVersion: '1.1.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $result = new ModuleUpdateResultDTO(
            moduleName: 'gallery',
            fromVersion: '1.0.0',
            toVersion: '1.1.0',
            status: UpdateStatus::Completed,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: null,
            backupPath: null,
            historyId: 'test-id',
        );

        $this->updater->shouldReceive('preview')
            ->andReturn($preview);

        $this->updater->shouldReceive('update')
            ->with(Mockery::on(fn ($arg) => $arg instanceof ModuleName && $arg->value === 'gallery'))
            ->andReturn($result);

        $this->artisan('module:update', ['name' => 'gallery', '--force' => true])
            ->expectsOutput('Starting update...')
            ->expectsOutput('Successfully updated gallery to 1.1.0')
            ->assertExitCode(0);
    }

    public function test_it_displays_major_update_warning(): void
    {
        $preview = new UpdatePreviewDTO(
            moduleName: 'payments',
            fromVersion: '1.9.9',
            toVersion: '2.0.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: true,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $this->updater->shouldReceive('preview')
            ->andReturn($preview);

        $this->artisan('module:update', ['name' => 'payments', '--dry-run' => true])
            ->expectsOutput('This is a major version update!')
            ->assertExitCode(0);
    }

    public function test_it_rejects_core_incompatible_update(): void
    {
        $preview = new UpdatePreviewDTO(
            moduleName: 'notifications',
            fromVersion: '1.0.0',
            toVersion: '2.0.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: true,
            coreCompatible: false,
            coreRequirement: '^3.0',
            downloadUrl: null,
            downloadSize: null,
        );

        $this->updater->shouldReceive('preview')
            ->andReturn($preview);

        $this->updater->shouldNotReceive('update');

        $this->artisan('module:update', ['name' => 'notifications', '--force' => true])
            ->expectsOutput('Incompatible with current core version. Requires: ^3.0')
            ->assertExitCode(1);
    }

    public function test_it_handles_update_failure(): void
    {
        $preview = new UpdatePreviewDTO(
            moduleName: 'events',
            fromVersion: '1.0.0',
            toVersion: '1.1.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $result = new ModuleUpdateResultDTO(
            moduleName: 'events',
            fromVersion: '1.0.0',
            toVersion: '1.1.0',
            status: UpdateStatus::Failed,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: 'Download failed: Connection timeout',
            backupPath: null,
            historyId: 'test-id',
        );

        $this->updater->shouldReceive('preview')
            ->andReturn($preview);

        $this->updater->shouldReceive('update')
            ->andReturn($result);

        $this->artisan('module:update', ['name' => 'events', '--force' => true])
            ->expectsOutput('Update failed: Download failed: Connection timeout')
            ->assertExitCode(1);
    }

    public function test_it_handles_rolled_back_update(): void
    {
        $preview = new UpdatePreviewDTO(
            moduleName: 'calendar',
            fromVersion: '1.0.0',
            toVersion: '1.2.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        );

        $result = new ModuleUpdateResultDTO(
            moduleName: 'calendar',
            fromVersion: '1.0.0',
            toVersion: '1.2.0',
            status: UpdateStatus::RolledBack,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: 'Health check failed',
            backupPath: '/backups/calendar.zip',
            historyId: 'test-id',
        );

        $this->updater->shouldReceive('preview')
            ->andReturn($preview);

        $this->updater->shouldReceive('update')
            ->andReturn($result);

        $this->artisan('module:update', ['name' => 'calendar', '--force' => true])
            ->expectsOutput('Update failed but was rolled back. Error: Health check failed')
            ->assertExitCode(1);
    }

    public function test_it_handles_preview_exception(): void
    {
        $this->updater->shouldReceive('preview')
            ->andThrow(UpdateException::noUpdateAvailable('missing-module'));

        $this->artisan('module:update', ['name' => 'missing-module'])
            ->expectsOutput("No update available for 'missing-module'.")
            ->assertExitCode(1);
    }

    public function test_update_all_modules(): void
    {
        $updates = new Collection([
            new AvailableUpdateDTO(
                moduleName: 'forum',
                displayName: 'Forum Module',
                currentVersion: '1.0.0',
                availableVersion: '1.1.0',
                releaseNotes: '',
                publishedAt: new DateTimeImmutable(),
                isPrerelease: false,
                isMajorUpdate: false,
                downloadUrl: '',
                hasChecksum: false,
            ),
            new AvailableUpdateDTO(
                moduleName: 'shop',
                displayName: 'Shop Module',
                currentVersion: '2.0.0',
                availableVersion: '2.1.0',
                releaseNotes: '',
                publishedAt: new DateTimeImmutable(),
                isPrerelease: false,
                isMajorUpdate: false,
                downloadUrl: '',
                hasChecksum: false,
            ),
        ]);

        $this->updateChecker->shouldReceive('checkAllForUpdates')
            ->andReturn($updates);

        $this->updater->shouldReceive('update')
            ->andReturn(
                new ModuleUpdateResultDTO(
                    moduleName: 'forum',
                    fromVersion: '1.0.0',
                    toVersion: '1.1.0',
                    status: UpdateStatus::Completed,
                    migrationsRun: [],
                    seedersRun: [],
                    errorMessage: null,
                    backupPath: null,
                    historyId: 'id-1',
                ),
                new ModuleUpdateResultDTO(
                    moduleName: 'shop',
                    fromVersion: '2.0.0',
                    toVersion: '2.1.0',
                    status: UpdateStatus::Completed,
                    migrationsRun: [],
                    seedersRun: [],
                    errorMessage: null,
                    backupPath: null,
                    historyId: 'id-2',
                )
            );

        $this->artisan('module:update', ['--all' => true, '--force' => true])
            ->expectsOutput('Found 2 update(s):')
            ->expectsOutput('Update complete: 2 succeeded, 0 failed.')
            ->assertExitCode(0);
    }

    public function test_update_all_shows_message_when_all_up_to_date(): void
    {
        $this->updateChecker->shouldReceive('checkAllForUpdates')
            ->andReturn(new Collection());

        $this->artisan('module:update', ['--all' => true])
            ->expectsOutput('All modules are up to date.')
            ->assertExitCode(0);
    }

    public function test_update_all_dry_run(): void
    {
        $updates = new Collection([
            new AvailableUpdateDTO(
                moduleName: 'forum',
                displayName: 'Forum Module',
                currentVersion: '1.0.0',
                availableVersion: '1.1.0',
                releaseNotes: '',
                publishedAt: new DateTimeImmutable(),
                isPrerelease: false,
                isMajorUpdate: false,
                downloadUrl: '',
                hasChecksum: false,
            ),
        ]);

        $this->updateChecker->shouldReceive('checkAllForUpdates')
            ->andReturn($updates);

        $this->updater->shouldNotReceive('update');

        $this->artisan('module:update', ['--all' => true, '--dry-run' => true])
            ->expectsOutput('Dry run - no changes made.')
            ->assertExitCode(0);
    }
}
