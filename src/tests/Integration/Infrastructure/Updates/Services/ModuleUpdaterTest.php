<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Updates\DTOs\PostUpdateReportDTO;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Application\Updates\Services\ModuleBackupServiceInterface;
use App\Application\Updates\Services\ModulePostUpdateRunnerInterface;
use App\Application\Updates\Services\ReleaseChannelPolicy;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use App\Domain\Updates\ValueObjects\ReleaseSelection;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleUpdateHistoryModel;
use App\Infrastructure\Updates\Services\ModulePackageInstaller;
use App\Infrastructure\Updates\Services\ModuleUpdater;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use ZipArchive;

final class ModuleUpdaterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MockInterface&ModuleRepositoryInterface $moduleRepository;

    private MockInterface&ModuleManagerServiceInterface $moduleManager;

    private MockInterface&GitHubReleaseFetcherInterface $githubFetcher;

    private MockInterface&ModuleBackupServiceInterface $backupService;

    private MockInterface&ModulePostUpdateRunnerInterface $postUpdateRunner;

    private MockInterface&Dispatcher $events;

    private ModuleUpdater $service;

    private string $tempDir;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleRepository = Mockery::mock(ModuleRepositoryInterface::class);
        $this->moduleManager = Mockery::mock(ModuleManagerServiceInterface::class);
        $this->githubFetcher = Mockery::mock(GitHubReleaseFetcherInterface::class);
        $this->backupService = Mockery::mock(ModuleBackupServiceInterface::class);
        $this->postUpdateRunner = Mockery::mock(ModulePostUpdateRunnerInterface::class);
        $this->events = Mockery::mock(Dispatcher::class);

        $this->service = new ModuleUpdater(
            $this->moduleRepository,
            $this->moduleManager,
            $this->githubFetcher,
            $this->backupService,
            $this->events,
            app(ModulePackageInstaller::class),
            new ReleaseChannelPolicy(allowPrereleases: false),
            $this->postUpdateRunner,
        );

        $this->tempDir = storage_path('app/test-updates');
        File::ensureDirectoryExists($this->tempDir);

        config(['updates.temp_path' => $this->tempDir]);
        config(['updates.behavior.verify_checksum' => false]);
        config(['updates.behavior.auto_rollback' => true]);

        // The updater reads these settings in its constructor
        $this->rebuildService();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        File::deleteDirectory(public_path('build/modules/updtest-game'));
        Cache::flush();
        parent::tearDown();
    }

    public function test_preview_returns_update_details(): void
    {
        $module = $this->createRealModule('forum', '1.0.0', true);
        $release = $this->createReleaseInfo('1.5.0');

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('selectRelease')
            ->andReturn(new ReleaseSelection($release, '^2.0', null, null, []));

        $preview = $this->service->preview(ModuleName::fromString('forum'));

        $this->assertEquals('forum', $preview->moduleName);
        $this->assertEquals('1.0.0', $preview->fromVersion);
        $this->assertEquals('1.5.0', $preview->toVersion);
        $this->assertFalse($preview->isMajorUpdate);
        $this->assertTrue($preview->coreCompatible);
    }

    public function test_preview_throws_when_module_not_found(): void
    {
        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn(null);

        $this->expectException(ModuleNotFoundException::class);

        $this->service->preview(ModuleName::fromString('nonexistent'));
    }

    public function test_preview_throws_when_no_update_source(): void
    {
        $module = $this->createRealModule('localpreview', '1.0.0', false);

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('no GitHub source configured');

        $this->service->preview(ModuleName::fromString('localpreview'));
    }

    public function test_preview_throws_when_no_update_available(): void
    {
        $module = $this->createRealModule('forum', '2.0.0', true);
        $release = $this->createReleaseInfo('1.5.0'); // Older than current

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('selectRelease')
            ->andReturn(new ReleaseSelection($release, '^2.0', null, null, []));

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('No update available');

        $this->service->preview(ModuleName::fromString('forum'));
    }

    public function test_preview_identifies_major_update(): void
    {
        $module = $this->createRealModule('forum', '1.9.9', true);
        $release = $this->createReleaseInfo('2.0.0');

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('selectRelease')
            ->andReturn(new ReleaseSelection($release, '^2.0', null, null, []));

        $preview = $this->service->preview(ModuleName::fromString('forum'));

        $this->assertTrue($preview->isMajorUpdate);
    }

    public function test_is_update_in_progress_returns_false_when_no_lock(): void
    {
        $result = $this->service->isUpdateInProgress(ModuleName::fromString('forum'));

        $this->assertFalse($result);
    }

    public function test_cancel_update_returns_true_when_lock_exists(): void
    {
        $lockKey = 'module_update_lock:forum';
        Cache::put($lockKey, true, 600);

        $result = $this->service->cancelUpdate(ModuleName::fromString('forum'));

        $this->assertTrue($result);
        $this->assertFalse(Cache::has($lockKey));
    }

    public function test_update_throws_when_lock_cannot_be_acquired(): void
    {
        // Simulate existing lock by using a real lock
        $lockKey = 'module_update_lock:lockedmod';
        $lock = Cache::lock($lockKey, 600);
        $lock->get(); // Acquire the lock

        $module = $this->createRealModule('lockedmod', '1.0.0', true);

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        // Allow events to be dispatched (for error handling)
        $this->events->shouldReceive('dispatch')->andReturnNull();

        try {
            $this->service->update(ModuleName::fromString('lockedmod'));
            $this->fail('Expected UpdateException to be thrown');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('lock', strtolower($e->getMessage()));
        } finally {
            $lock->release();
        }
    }

    public function test_update_throws_when_module_not_found(): void
    {
        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn(null);

        $this->expectException(ModuleNotFoundException::class);

        $this->service->update(ModuleName::fromString('nonexistent'));
    }

    public function test_update_throws_when_no_source_configured(): void
    {
        $module = $this->createRealModule('localupdate', '1.0.0', false);

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('no GitHub source configured');

        $this->service->update(ModuleName::fromString('localupdate'));
    }

    public function test_rollback_throws_when_module_not_found(): void
    {
        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn(null);

        $this->expectException(ModuleNotFoundException::class);

        $this->service->rollback(ModuleName::fromString('nonexistent'), '/path/to/backup.zip');
    }

    public function test_update_installs_new_version_and_keeps_siblings(): void
    {
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertTrue($result->isSuccess(), (string) $result->errorMessage);
        $this->assertSame('1.0.1-beta', $this->manifestVersion('updtest-game'));
        $this->assertSame('updtest-aaa', json_decode(File::get($this->modulesPath.'/updtest-aaa/module.json'), true)['name']);
        $this->assertSame([], glob($this->modulesPath.'/.*updtest-game-*', GLOB_ONLYDIR));
        $this->assertSame([], glob($this->tempDir.'/*.zip'));
        $this->assertSame('new', File::get(public_path('build/modules/updtest-game/manifest.json')));
    }

    public function test_update_keeps_the_module_enabled_while_it_runs(): void
    {
        // Disabling rebuilt the config cache in the worker, which dropped the module's bindings mid-update
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));
        $this->moduleManager->shouldNotReceive('disable');
        $this->moduleManager->shouldNotReceive('enable');

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertTrue($result->isSuccess(), (string) $result->errorMessage);
    }

    public function test_update_runs_the_post_update_steps_once_the_new_files_are_in_place(): void
    {
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));
        $versionSeenByRunner = null;
        $this->postUpdateRunner->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn (ModuleName $name): bool => $name->value === 'updtest-game'),
                Mockery::on(fn (ModuleVersion $version): bool => $version->value() === '1.0.1-beta'),
            )
            ->andReturnUsing(function () use (&$versionSeenByRunner): PostUpdateReportDTO {
                $versionSeenByRunner = $this->manifestVersion('updtest-game');

                return new PostUpdateReportDTO([], []);
            });

        $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertSame('1.0.1-beta', $versionSeenByRunner);
    }

    public function test_update_result_lists_the_migrations_and_seeders_the_post_update_steps_applied(): void
    {
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));
        $this->postUpdateRunner->shouldReceive('run')
            ->andReturn(new PostUpdateReportDTO(['2026_10_04_000000_create_updtest_game_items_table'], ['ItemsSeeder']));

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertSame(['2026_10_04_000000_create_updtest_game_items_table'], $result->migrationsRun);
        $this->assertSame(['ItemsSeeder'], $result->seedersRun);
    }

    public function test_update_refreshes_caches_only_after_the_post_update_steps_succeed(): void
    {
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));
        $this->postUpdateRunner->shouldReceive('refreshCaches')->once();

        $this->service->update(ModuleName::fromString('updtest-game'));
    }

    public function test_update_does_not_refresh_caches_when_the_post_update_steps_fail(): void
    {
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));
        $this->postUpdateRunner->shouldReceive('run')->andThrow(UpdateException::postUpdateFailed('updtest-game', 'broken'));
        $this->postUpdateRunner->shouldNotReceive('refreshCaches');

        $this->service->update(ModuleName::fromString('updtest-game'));
    }

    public function test_update_reverts_files_and_assets_when_the_post_update_steps_fail(): void
    {
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));
        $this->postUpdateRunner->shouldReceive('run')
            ->andThrow(UpdateException::postUpdateFailed('updtest-game', 'Target class [PublishersSeeder] does not exist.'));

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertTrue($result->wasRolledBack());
        $this->assertSame('1.0.0-beta', $this->manifestVersion('updtest-game'));
        $this->assertSame('old', File::get(public_path('build/modules/updtest-game/manifest.json')));
        $this->assertSame([], glob($this->modulesPath.'/.*updtest-game-*', GLOB_ONLYDIR));
        $this->assertSame([], glob($this->tempDir.'/*.zip'));
    }

    public function test_a_rolled_back_update_keeps_the_error_in_its_history(): void
    {
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));
        $this->postUpdateRunner->shouldReceive('run')
            ->andThrow(UpdateException::postUpdateFailed('updtest-game', 'Target class [PublishersSeeder] does not exist.'));

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $history = ModuleUpdateHistoryModel::findOrFail($result->historyId);
        $this->assertStringContainsString('Target class [PublishersSeeder] does not exist.', (string) $history->error_message);
    }

    public function test_update_rejecting_the_package_leaves_the_module_enabled_and_untouched(): void
    {
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-aaa', '1.0.1-beta', 'new'));
        $this->postUpdateRunner->shouldNotReceive('run');

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertFalse($result->isSuccess());
        $this->assertSame('1.0.0-beta', $this->manifestVersion('updtest-game'));
        $this->assertSame([], glob($this->tempDir.'/*.zip'));
    }

    public function test_update_fails_closed_when_checksum_is_required_but_missing(): void
    {
        config(['updates.behavior.verify_checksum' => true]);
        $this->rebuildService();
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));
        $this->postUpdateRunner->shouldNotReceive('run');

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertFalse($result->isSuccess());
        $this->assertSame('1.0.0-beta', $this->manifestVersion('updtest-game'));
    }

    public function test_update_requests_prereleases_for_beta_installs(): void
    {
        $this->prepareInstalledModules();
        $this->events->shouldReceive('dispatch')->andReturnNull();
        $this->githubFetcher->shouldReceive('selectRelease')
            ->with('owner', 'updtest-game', Mockery::type(ModuleVersion::class), true)
            ->once()
            ->andReturn(ReleaseSelection::none());

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertFalse($result->isSuccess()); // noUpdateAvailable is caught and returned as a failed result
    }

    public function test_an_incompatible_release_fails_in_staging_and_never_touches_the_installed_module(): void
    {
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new', ['core' => '^99.0']));
        $this->postUpdateRunner->shouldNotReceive('run');

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertSame(UpdateStatus::Failed, $result->status);
        $this->assertStringContainsString('requires core ^99.0', (string) $result->errorMessage);
        $this->assertStringContainsString('requires core ^99.0', (string) ModuleUpdateHistoryModel::findOrFail($result->historyId)->error_message);
        $this->assertSame('1.0.0-beta', $this->manifestVersion('updtest-game'));
        $this->assertSame('old', File::get(public_path('build/modules/updtest-game/manifest.json')));
        $this->assertSame([], glob($this->modulesPath.'/.*updtest-game-*', GLOB_ONLYDIR));
    }

    public function test_preview_of_an_only_blocked_release_explains_why(): void
    {
        $this->moduleRepository->shouldReceive('findByName')->andReturn($this->createRealModule('forum', '1.1.0', true));
        $issue = new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED);
        $this->githubFetcher->shouldReceive('selectRelease')->andReturn(
            new ReleaseSelection(null, null, $this->createReleaseInfo('2.0.0'), '^3.0', [$issue]),
        );

        $preview = $this->service->preview(ModuleName::fromString('forum'));

        $this->assertSame('2.0.0', $preview->toVersion);
        $this->assertFalse($preview->coreCompatible);
        $this->assertSame('^3.0', $preview->coreRequirement);
        $this->assertSame([$issue->toArray()], $preview->compatibilityIssues);
    }

    public function test_preview_prefers_the_compatible_release(): void
    {
        $this->moduleRepository->shouldReceive('findByName')->andReturn($this->createRealModule('forum', '1.1.0', true));
        $this->githubFetcher->shouldReceive('selectRelease')->andReturn(new ReleaseSelection(
            $this->createReleaseInfo('1.2.0'),
            '^2.6',
            $this->createReleaseInfo('2.0.0'),
            '^3.0',
            [new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED)],
        ));

        $preview = $this->service->preview(ModuleName::fromString('forum'));

        $this->assertSame('1.2.0', $preview->toVersion);
        $this->assertTrue($preview->coreCompatible);
        $this->assertSame('^2.6', $preview->coreRequirement);
        $this->assertSame([], $preview->compatibilityIssues);
    }

    public function test_update_never_applies_a_blocked_release(): void
    {
        $this->prepareInstalledModules();
        $this->events->shouldReceive('dispatch')->andReturnNull();
        $this->githubFetcher->shouldReceive('selectRelease')->andReturn(new ReleaseSelection(
            null,
            null,
            $this->createReleaseInfo('2.0.0'),
            '^3.0',
            [new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED)],
        ));
        $this->githubFetcher->shouldNotReceive('downloadRelease');

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertSame(UpdateStatus::Failed, $result->status);
        $this->assertSame("No compatible update for 'updtest-game': 2.0.0 is available but requires core ^3.0, found 2.6.0.", $result->errorMessage);
        $this->assertSame('1.0.0-beta', $this->manifestVersion('updtest-game'));
    }

    private function prepareInstalledModules(): Module
    {
        $this->modulesPath = $this->tempDir.'/modules';
        config(['modules.path' => $this->modulesPath]);

        foreach (['updtest-game', 'updtest-aaa'] as $name) {
            File::ensureDirectoryExists("{$this->modulesPath}/{$name}/public/build");
            File::put("{$this->modulesPath}/{$name}/module.json", (string) json_encode(['name' => $name, 'version' => '1.0.0-beta']));
            File::put("{$this->modulesPath}/{$name}/public/build/manifest.json", 'old');
        }

        File::ensureDirectoryExists(public_path('build/modules/updtest-game'));
        File::put(public_path('build/modules/updtest-game/manifest.json'), 'old');

        $module = new Module(
            id: new ModuleId(Str::uuid()->toString()),
            name: ModuleName::fromString('updtest-game'),
            displayName: 'Updtest game',
            description: 'Test module',
            version: ModuleVersion::fromString('1.0.0-beta'),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
            path: "{$this->modulesPath}/updtest-game",
            sourceOwner: 'owner',
            sourceRepo: 'updtest-game',
        );

        $this->moduleRepository->shouldReceive('findByName')->andReturn($module);
        $this->moduleRepository->shouldReceive('save');
        $this->backupService->shouldReceive('createBackup')->andReturn($this->tempDir.'/backup.zip');
        $this->postUpdateRunner->shouldReceive('run')->andReturn(new PostUpdateReportDTO([], []))->byDefault();
        $this->postUpdateRunner->shouldReceive('refreshCaches')->byDefault();

        return $module;
    }

    private function expectRelease(string $version, string $zipPath): void
    {
        $this->events->shouldReceive('dispatch')->andReturnNull();
        $this->githubFetcher->shouldReceive('selectRelease')->andReturn(new ReleaseSelection($this->createReleaseInfo($version), '^2.0', null, null, []));
        $this->githubFetcher->shouldReceive('downloadRelease')->andReturnUsing(
            function (GitHubReleaseInfo $release, string $destination) use ($zipPath): string {
                File::ensureDirectoryExists(dirname($destination));
                File::copy($zipPath, $destination);

                return $destination;
            }
        );
    }

    /**
     * @param  array<string, mixed>  $requires
     */
    private function releaseZip(string $name, string $version, string $assetContent, array $requires = []): string
    {
        $path = $this->tempDir.'/fixtures/'.uniqid().'.release';
        File::ensureDirectoryExists(dirname($path));
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString("{$name}-{$version}/module.json", (string) json_encode([
            'name' => $name,
            'version' => $version,
            'namespace' => 'Modules\\'.str_replace('-', '', ucwords($name, '-')),
            'provider' => str_replace('-', '', ucwords($name, '-')).'ServiceProvider',
            'requires' => $requires,
        ]));
        $zip->addFromString("{$name}-{$version}/public/build/manifest.json", $assetContent);
        $zip->close();

        return $path;
    }

    private function manifestVersion(string $name): string
    {
        return json_decode(File::get("{$this->modulesPath}/{$name}/module.json"), true)['version'];
    }

    private function rebuildService(): void
    {
        $this->service = new ModuleUpdater(
            $this->moduleRepository,
            $this->moduleManager,
            $this->githubFetcher,
            $this->backupService,
            $this->events,
            app(ModulePackageInstaller::class),
            new ReleaseChannelPolicy(allowPrereleases: false),
            $this->postUpdateRunner,
        );
    }

    private function createRealModule(
        string $name,
        string $version,
        bool $hasSource
    ): Module {
        return new Module(
            id: new ModuleId(Str::uuid()->toString()),
            name: ModuleName::fromString($name),
            displayName: ucfirst($name).' Module',
            description: 'Test module',
            version: ModuleVersion::fromString($version),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
            path: "{$this->tempDir}/{$name}",
            sourceOwner: $hasSource ? 'owner' : null,
            sourceRepo: $hasSource ? $name : null,
        );
    }

    private function createReleaseInfo(string $version): GitHubReleaseInfo
    {
        return new GitHubReleaseInfo(
            tagName: "v{$version}",
            version: ModuleVersion::fromString($version),
            downloadUrl: "https://github.com/owner/repo/releases/download/v{$version}/module.zip",
            checksumUrl: '',
            releaseNotes: 'Test release notes',
            publishedAt: new DateTimeImmutable,
            isPrerelease: false,
        );
    }
}
