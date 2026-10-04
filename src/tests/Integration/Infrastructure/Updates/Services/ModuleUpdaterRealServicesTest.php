<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Updates\DTOs\HealthCheckResultDTO;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Application\Updates\Services\ModuleBackupServiceInterface;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Application\Updates\Services\ModulePostUpdateRunnerInterface;
use App\Application\Updates\Services\ReleaseChannelPolicy;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleUpdateHistoryModel;
use App\Infrastructure\Updates\Services\ModulePackageInstaller;
use App\Infrastructure\Updates\Services\ModuleUpdater;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use ZipArchive;

/**
 * Exercises the updater with the real module repository and manager and the real
 * module:finish-update command (run in-process here), mocking only GitHub, backups and
 * the health check, so entity/database drift cannot hide behind mocks.
 */
final class ModuleUpdaterRealServicesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $tempDir;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->tempDir = sys_get_temp_dir().'/gf-real-update-'.uniqid();
        $this->modulesPath = $this->tempDir.'/modules';
        File::ensureDirectoryExists("{$this->modulesPath}/real-mod/src");
        File::put("{$this->modulesPath}/real-mod/module.json", (string) json_encode(['name' => 'real-mod', 'version' => '1.0.0-beta']));

        config([
            'modules.path' => $this->modulesPath,
            'updates.temp_path' => $this->tempDir.'/tmp',
            'updates.behavior.verify_checksum' => false,
            'updates.behavior.health_check' => true,
            'updates.behavior.auto_rollback' => true,
        ]);

        ModuleModel::factory()->enabled()->create([
            'name' => 'real-mod',
            'version' => '1.0.0-beta',
            'source_owner' => 'o',
            'source_repo' => 'real-mod',
            'path' => "{$this->modulesPath}/real-mod",
            'namespace' => 'Modules\\RealMod',
            'provider' => 'RealModServiceProvider',
            'installed_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        File::deleteDirectory(public_path('build/modules/real-mod'));
        parent::tearDown();
    }

    public function test_update_completes_and_keeps_database_and_files_in_sync(): void
    {
        $result = $this->updater(healthy: true)->update(ModuleName::fromString('real-mod'));

        $row = ModuleModel::query()->where('name', 'real-mod')->firstOrFail();
        $this->assertSame(UpdateStatus::Completed, $result->status, (string) $result->errorMessage);
        $this->assertSame('1.0.1-beta', $row->version);
        $this->assertSame('enabled', $row->status instanceof \BackedEnum ? $row->status->value : $row->status);
        $this->assertSame('1.0.1-beta', $this->diskVersion());
    }

    public function test_failed_update_leaves_database_version_matching_the_restored_files(): void
    {
        $result = $this->updater(healthy: false)->update(ModuleName::fromString('real-mod'));

        $row = ModuleModel::query()->where('name', 'real-mod')->firstOrFail();
        $this->assertSame(UpdateStatus::RolledBack, $result->status);
        $this->assertSame('1.0.0-beta', $this->diskVersion());
        $this->assertSame('1.0.0-beta', $row->version);
        $this->assertSame('enabled', $row->status instanceof \BackedEnum ? $row->status->value : $row->status);
        $this->assertStringContainsString(
            'provider failed',
            (string) ModuleUpdateHistoryModel::findOrFail($result->historyId)->error_message,
        );
    }

    private function updater(bool $healthy): ModuleUpdater
    {
        $zip = $this->tempDir.'/release.zip';
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('real-mod-1.0.1-beta/module.json', (string) json_encode(['name' => 'real-mod', 'version' => '1.0.1-beta']));
        $archive->close();

        $fetcher = Mockery::mock(GitHubReleaseFetcherInterface::class);
        $fetcher->shouldReceive('getLatestRelease')->andReturn(new GitHubReleaseInfo(
            'v1.0.1-beta', ModuleVersion::fromString('1.0.1-beta'), 'https://example.test/real-mod.zip', '', '', new DateTimeImmutable, true,
        ));
        $fetcher->shouldReceive('downloadRelease')->andReturnUsing(function (GitHubReleaseInfo $release, string $destination) use ($zip): string {
            File::ensureDirectoryExists(dirname($destination));
            File::copy($zip, $destination);

            return $destination;
        });

        $backup = Mockery::mock(ModuleBackupServiceInterface::class);
        $backup->shouldReceive('createBackup')->andReturn($this->tempDir.'/backup.zip');

        $health = Mockery::mock(ModuleHealthCheckerInterface::class);
        $health->shouldReceive('check')->andReturn($healthy
            ? new HealthCheckResultDTO(true, true, true)
            : new HealthCheckResultDTO(false, true, true, ['provider failed']));
        $this->app->instance(ModuleHealthCheckerInterface::class, $health);

        $inProcessRunner = new class implements ModulePostUpdateRunnerInterface
        {
            public function run(ModuleName $moduleName): void
            {
                if (Artisan::call('module:finish-update', ['name' => $moduleName->value]) !== 0) {
                    throw UpdateException::postUpdateFailed($moduleName->value, trim(Artisan::output()));
                }
            }
        };

        return new ModuleUpdater(
            app(ModuleRepositoryInterface::class),
            app(ModuleManagerServiceInterface::class),
            $fetcher,
            $backup,
            app(Dispatcher::class),
            new ModulePackageInstaller,
            new ReleaseChannelPolicy(allowPrereleases: false),
            $inProcessRunner,
        );
    }

    private function diskVersion(): string
    {
        return json_decode(File::get("{$this->modulesPath}/real-mod/module.json"), true)['version'];
    }
}
