<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Updates\DTOs\HealthCheckResultDTO;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Application\Updates\Services\ModuleBackupServiceInterface;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Application\Updates\Services\ReleaseChannelPolicy;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
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

    private MockInterface&ModuleHealthCheckerInterface $healthChecker;

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
        $this->healthChecker = Mockery::mock(ModuleHealthCheckerInterface::class);
        $this->events = Mockery::mock(Dispatcher::class);

        $this->service = new ModuleUpdater(
            $this->moduleRepository,
            $this->moduleManager,
            $this->githubFetcher,
            $this->backupService,
            $this->healthChecker,
            $this->events,
            new ModulePackageInstaller,
            new ReleaseChannelPolicy(allowPrereleases: false),
        );

        $this->tempDir = storage_path('app/test-updates');
        File::ensureDirectoryExists($this->tempDir);

        config(['updates.temp_path' => $this->tempDir]);
        config(['updates.behavior.verify_checksum' => false]);
        config(['updates.behavior.health_check' => false]);
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

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->andReturn($release);

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

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->andReturn($release);

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

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->andReturn($release);

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
        $module = $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));
        $this->moduleManager->shouldReceive('disable')->once()->andReturn($module);
        $this->moduleManager->shouldReceive('enable')->once()->andReturn($module);

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertTrue($result->isSuccess(), (string) $result->errorMessage);
        $this->assertSame('1.0.1-beta', $this->manifestVersion('updtest-game'));
        $this->assertSame('1.0.1-beta', $module->version()->value());
        $this->assertSame('updtest-aaa', json_decode(File::get($this->modulesPath.'/updtest-aaa/module.json'), true)['name']);
        $this->assertSame([], glob($this->modulesPath.'/.*updtest-game-*', GLOB_ONLYDIR));
        $this->assertSame([], glob($this->tempDir.'/*.zip'));
        $this->assertSame('new', File::get(public_path('build/modules/updtest-game/manifest.json')));
    }

    public function test_update_reverts_files_and_assets_when_health_check_fails(): void
    {
        config(['updates.behavior.health_check' => true]);
        $this->rebuildService();
        $module = $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-game', '1.0.1-beta', 'new'));
        $this->moduleManager->shouldReceive('disable')->once()->andReturn($module);
        $this->moduleManager->shouldReceive('enable')->once()->andReturn($module);
        $this->healthChecker->shouldReceive('check')->andReturn(new HealthCheckResultDTO(false, true, true, ['provider failed']));

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertTrue($result->wasRolledBack());
        $this->assertSame('1.0.0-beta', $this->manifestVersion('updtest-game'));
        $this->assertSame('old', File::get(public_path('build/modules/updtest-game/manifest.json')));
        $this->assertSame([], glob($this->modulesPath.'/.*updtest-game-*', GLOB_ONLYDIR));
        $this->assertSame([], glob($this->tempDir.'/*.zip'));
    }

    public function test_update_rejecting_the_package_leaves_the_module_enabled_and_untouched(): void
    {
        $this->prepareInstalledModules();
        $this->expectRelease('1.0.1-beta', $this->releaseZip('updtest-aaa', '1.0.1-beta', 'new'));
        $this->moduleManager->shouldNotReceive('disable');

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
        $this->moduleManager->shouldNotReceive('disable');

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertFalse($result->isSuccess());
        $this->assertSame('1.0.0-beta', $this->manifestVersion('updtest-game'));
    }

    public function test_update_requests_prereleases_for_beta_installs(): void
    {
        $this->prepareInstalledModules();
        $this->events->shouldReceive('dispatch')->andReturnNull();
        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->with('owner', 'updtest-game', true)
            ->once()
            ->andReturn(null);

        $result = $this->service->update(ModuleName::fromString('updtest-game'));

        $this->assertFalse($result->isSuccess()); // noUpdateAvailable is caught and returned as a failed result
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
        $this->healthChecker->shouldReceive('check')->andReturn(new HealthCheckResultDTO(true, true, true))->byDefault();

        return $module;
    }

    private function expectRelease(string $version, string $zipPath): void
    {
        $this->events->shouldReceive('dispatch')->andReturnNull();
        $this->githubFetcher->shouldReceive('getLatestRelease')->andReturn($this->createReleaseInfo($version));
        $this->githubFetcher->shouldReceive('downloadRelease')->andReturnUsing(
            function (GitHubReleaseInfo $release, string $destination) use ($zipPath): string {
                File::ensureDirectoryExists(dirname($destination));
                File::copy($zipPath, $destination);

                return $destination;
            }
        );
    }

    private function releaseZip(string $name, string $version, string $assetContent): string
    {
        $path = $this->tempDir.'/fixtures/'.uniqid().'.release';
        File::ensureDirectoryExists(dirname($path));
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString("{$name}-{$version}/module.json", (string) json_encode(['name' => $name, 'version' => $version]));
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
            $this->healthChecker,
            $this->events,
            new ModulePackageInstaller,
            new ReleaseChannelPolicy(allowPrereleases: false),
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
