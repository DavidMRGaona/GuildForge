<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Updates\DTOs\HealthCheckResultDTO;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Application\Updates\Services\ModuleBackupServiceInterface;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Domain\Updates\Events\ModuleUpdateCompleted;
use App\Domain\Updates\Events\ModuleUpdateFailed;
use App\Domain\Updates\Events\ModuleUpdateStarted;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
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
            $this->events
        );

        $this->tempDir = storage_path('app/test-updates');
        File::ensureDirectoryExists($this->tempDir);

        config(['updates.temp_path' => $this->tempDir]);
        config(['updates.behavior.verify_checksum' => false]);
        config(['updates.behavior.health_check' => false]);
        config(['updates.behavior.auto_rollback' => true]);

        Cache::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
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

    private function createRealModule(
        string $name,
        string $version,
        bool $hasSource
    ): Module {
        return new Module(
            id: new ModuleId(Str::uuid()->toString()),
            name: ModuleName::fromString($name),
            displayName: ucfirst($name) . ' Module',
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
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );
    }
}
