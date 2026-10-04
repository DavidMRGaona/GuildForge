<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Updates\DTOs\ModuleUpdateResultDTO;
use App\Application\Updates\DTOs\UpdatePreviewDTO;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Application\Updates\Services\ModuleBackupServiceInterface;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Application\Updates\Services\ModulePackageInstallerInterface;
use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Application\Updates\Services\ReleaseChannelPolicy;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Domain\Updates\Events\ModuleUpdateCompleted;
use App\Domain\Updates\Events\ModuleUpdateFailed;
use App\Domain\Updates\Events\ModuleUpdateStarted;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleSeederHistoryModel;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleUpdateHistoryModel;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleUpdateLogModel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ModuleUpdater implements ModuleUpdaterInterface
{
    private const string LOCK_PREFIX = 'module_update_lock:';

    private const int LOCK_TTL = 600; // 10 minutes

    private readonly string $tempPath;

    private readonly bool $verifyChecksum;

    private readonly bool $healthCheck;

    private readonly bool $autoRollback;

    public function __construct(
        private readonly ModuleRepositoryInterface $moduleRepository,
        private readonly ModuleManagerServiceInterface $moduleManager,
        private readonly GitHubReleaseFetcherInterface $githubFetcher,
        private readonly ModuleBackupServiceInterface $backupService,
        private readonly ModuleHealthCheckerInterface $healthChecker,
        private readonly Dispatcher $events,
        private readonly ModulePackageInstallerInterface $installer,
        private readonly ReleaseChannelPolicy $channelPolicy,
    ) {
        $this->tempPath = (string) config('updates.temp_path', storage_path('app/temp/updates'));
        $this->verifyChecksum = (bool) config('updates.behavior.verify_checksum', true);
        $this->healthCheck = (bool) config('updates.behavior.health_check', true);
        $this->autoRollback = (bool) config('updates.behavior.auto_rollback', true);
    }

    public function preview(ModuleName $name): UpdatePreviewDTO
    {
        $module = $this->moduleRepository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        if (! $module->hasUpdateSource()) {
            throw UpdateException::noSourceConfigured($name->value);
        }

        $sourceOwner = $module->sourceOwner();
        $sourceRepo = $module->sourceRepo();

        if ($sourceOwner === null || $sourceRepo === null) {
            throw UpdateException::noSourceConfigured($name->value);
        }

        $release = $this->githubFetcher->getLatestRelease(
            $sourceOwner,
            $sourceRepo,
            $this->channelPolicy->includesPrereleasesFor($module->version()),
        );

        if ($release === null || ! $release->version->isGreaterThan($module->version())) {
            throw UpdateException::noUpdateAvailable($name->value);
        }

        // Check core compatibility
        $coreCompatible = true;
        $coreRequirement = null;

        // Look for migrations and seeders in the release notes or manifest
        $pendingMigrations = [];
        $newSeeders = [];

        return new UpdatePreviewDTO(
            moduleName: $name->value,
            fromVersion: $module->version()->value(),
            toVersion: $release->version->value(),
            pendingMigrations: $pendingMigrations,
            newSeeders: $newSeeders,
            changelog: $release->releaseNotes,
            isMajorUpdate: $release->isMajorUpgradeFrom($module->version()),
            coreCompatible: $coreCompatible,
            coreRequirement: $coreRequirement,
            downloadUrl: $release->downloadUrl,
            downloadSize: null,
        );
    }

    public function update(ModuleName $name): ModuleUpdateResultDTO
    {
        $module = $this->moduleRepository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        if (! $module->hasUpdateSource()) {
            throw UpdateException::noSourceConfigured($name->value);
        }

        // Acquire lock
        $lockKey = self::LOCK_PREFIX.$name->value;
        $lock = Cache::lock($lockKey, self::LOCK_TTL);

        if (! $lock->get()) {
            throw UpdateException::lockAcquisitionFailed($name->value);
        }

        $fromVersion = $module->version()->value();
        $history = $this->createHistoryRecord($name->value, $fromVersion);
        $backupPath = null;
        $downloadPath = null;
        $stagedRoot = null;
        $previousPath = null;
        $wasDisabled = false;
        $migrationsRun = [];
        $seedersRun = [];

        try {
            // Interrupted runs may have left staging/previous copies behind
            $this->installer->cleanupLeftovers($name->value);

            /** @var string $sourceOwner */
            $sourceOwner = $module->sourceOwner();
            /** @var string $sourceRepo */
            $sourceRepo = $module->sourceRepo();

            $release = $this->githubFetcher->getLatestRelease(
                $sourceOwner,
                $sourceRepo,
                $this->channelPolicy->includesPrereleasesFor($module->version()),
            );

            if ($release === null || ! $release->version->isGreaterThan($module->version())) {
                throw UpdateException::noUpdateAvailable($name->value);
            }

            $toVersion = $release->version->value();
            $history->to_version = $toVersion;
            $history->save();

            $this->events->dispatch(new ModuleUpdateStarted($name->value, $fromVersion, $toVersion));

            // Step 1: Backup (manual safety net; automatic rollback uses the previous directory)
            $this->updateStatus($history, UpdateStatus::BackingUp);
            $this->log($history->id, 'backup', 'started', 'Creating backup...');
            $backupPath = $this->backupService->createBackup($name);
            $history->backup_path = $backupPath;
            $history->save();
            $this->log($history->id, 'backup', 'completed', "Backup created at {$backupPath}");

            // Step 2: Download
            $this->updateStatus($history, UpdateStatus::Downloading);
            $this->log($history->id, 'download', 'started', 'Downloading update...');
            $downloadPath = "{$this->tempPath}/{$name->value}-{$toVersion}.zip";
            $this->githubFetcher->downloadRelease($release, $downloadPath);
            $this->log($history->id, 'download', 'completed', 'Download complete');

            // Step 3: Verify checksum (fail closed when verification is required)
            if ($this->verifyChecksum) {
                if (! $release->hasChecksum()) {
                    throw UpdateException::checksumFetchFailed($name->value);
                }

                $this->updateStatus($history, UpdateStatus::Verifying);
                $this->log($history->id, 'verify', 'started', 'Verifying checksum...');
                $this->githubFetcher->fetchAndVerifyChecksum($release, $downloadPath);
                $this->log($history->id, 'verify', 'completed', 'Checksum verified');
            }

            // Step 4: Stage the package; the installed module is untouched until the swap
            $this->updateStatus($history, UpdateStatus::Applying);
            $this->log($history->id, 'apply', 'started', 'Applying update...');
            $stagedRoot = $this->installer->stage($downloadPath, $name->value);

            // Step 5: Disable and swap
            if ($module->isEnabled()) {
                $this->moduleManager->disable($name);
                $wasDisabled = true;
            }

            $previousPath = $this->installer->swap($stagedRoot, $name->value);
            $stagedRoot = null;
            $this->publishPreBuiltAssets($module->path(), $name->value);
            $this->log($history->id, 'apply', 'completed', 'Update applied');

            // Step 6: Migrations and new seeders
            $this->updateStatus($history, UpdateStatus::Migrating);
            $this->log($history->id, 'migrate', 'started', 'Running migrations...');

            DB::transaction(function () use ($name, &$migrationsRun, &$seedersRun, $history) {
                $migrationsRun = $this->runMigrations($name);
                $this->log($history->id, 'migrate', 'completed', 'Migrations complete', ['count' => count($migrationsRun)]);

                $this->updateStatus($history, UpdateStatus::Seeding);
                $this->log($history->id, 'seed', 'started', 'Running seeders...');
                $seedersRun = $this->runNewSeeders($name);
                $this->log($history->id, 'seed', 'completed', 'Seeders complete', ['count' => count($seedersRun)]);
            });

            // Step 7: Health check
            if ($this->healthCheck) {
                $this->updateStatus($history, UpdateStatus::HealthChecking);
                $this->log($history->id, 'health', 'started', 'Running health check...');
                $healthResult = $this->healthChecker->check($name);

                if (! $healthResult->passes()) {
                    throw UpdateException::healthCheckFailed($name->value, implode(', ', $healthResult->errors));
                }

                $this->log($history->id, 'health', 'completed', 'Health check passed');
            }

            // Step 8: Persist the new version, re-enable and drop the previous copy
            $module->updateVersion(ModuleVersion::fromString($toVersion));
            $module->clearLatestAvailableVersion();
            $this->moduleRepository->save($module);

            if ($wasDisabled) {
                $this->moduleManager->enable($name);
            }

            $this->installer->discard($previousPath);
            $previousPath = null;

            $this->updateStatus($history, UpdateStatus::Completed);
            $history->markCompleted();
            $this->log($history->id, 'complete', 'completed', 'Update completed successfully');

            $this->events->dispatch(new ModuleUpdateCompleted($name->value, $fromVersion, $toVersion));

            return new ModuleUpdateResultDTO(
                moduleName: $name->value,
                fromVersion: $fromVersion,
                toVersion: $toVersion,
                status: UpdateStatus::Completed,
                migrationsRun: $migrationsRun,
                seedersRun: $seedersRun,
                errorMessage: null,
                backupPath: $backupPath,
                historyId: $history->id,
            );
        } catch (\Throwable $e) {
            Log::error("Module update failed for {$name->value}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->log($history->id, 'error', 'failed', $e->getMessage());

            $wasRolledBack = false;

            if ($previousPath !== null && $this->autoRollback) {
                try {
                    $this->log($history->id, 'rollback', 'started', 'Restoring the previous version...');
                    $this->installer->revert($previousPath, $name->value);
                    $previousPath = null;
                    $this->publishPreBuiltAssets($module->path(), $name->value);
                    $wasRolledBack = true;
                    $history->markRolledBack();
                    $this->log($history->id, 'rollback', 'completed', 'Previous version restored');
                } catch (\Throwable $rollbackError) {
                    Log::error("Rollback failed for {$name->value}", ['error' => $rollbackError->getMessage()]);
                    $this->log($history->id, 'rollback', 'failed', $rollbackError->getMessage());
                }
            }

            if ($wasDisabled) {
                try {
                    $this->moduleManager->enable($name);
                } catch (\Throwable $enableError) {
                    Log::error("Could not re-enable {$name->value} after a failed update", ['error' => $enableError->getMessage()]);
                }
            }

            if (! $wasRolledBack) {
                $history->markFailed($e->getMessage());
            }

            $this->events->dispatch(new ModuleUpdateFailed(
                $name->value,
                $fromVersion,
                $history->to_version ?? 'unknown',
                $e->getMessage(),
                $wasRolledBack,
            ));

            return new ModuleUpdateResultDTO(
                moduleName: $name->value,
                fromVersion: $fromVersion,
                toVersion: $history->to_version ?? 'unknown',
                status: $wasRolledBack ? UpdateStatus::RolledBack : UpdateStatus::Failed,
                migrationsRun: $migrationsRun,
                seedersRun: $seedersRun,
                errorMessage: $e->getMessage(),
                backupPath: $backupPath,
                historyId: $history->id,
            );
        } finally {
            // Whatever happened, nothing temporary survives this run
            if ($downloadPath !== null && File::exists($downloadPath)) {
                File::delete($downloadPath);
            }

            if ($stagedRoot !== null) {
                $this->installer->discard(dirname($stagedRoot));
            }

            $lock->release();
        }
    }

    public function rollback(ModuleName $name, string $backupPath): ModuleUpdateResultDTO
    {
        $module = $this->moduleRepository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        $history = $this->createHistoryRecord($name->value, $module->version()->value());
        $history->to_version = 'rollback';
        $history->backup_path = $backupPath;
        $history->save();

        try {
            $this->log($history->id, 'rollback', 'started', "Rolling back from backup: {$backupPath}");

            if ($module->isEnabled()) {
                $this->moduleManager->disable($name);
            }

            $this->backupService->restoreBackup($name, $backupPath);
            $this->moduleManager->enable($name);

            $history->markCompleted();
            $this->log($history->id, 'rollback', 'completed', 'Rollback successful');

            return new ModuleUpdateResultDTO(
                moduleName: $name->value,
                fromVersion: $module->version()->value(),
                toVersion: 'rollback',
                status: UpdateStatus::Completed,
                migrationsRun: [],
                seedersRun: [],
                errorMessage: null,
                backupPath: $backupPath,
                historyId: $history->id,
            );
        } catch (\Throwable $e) {
            $history->markFailed($e->getMessage());
            $this->log($history->id, 'rollback', 'failed', $e->getMessage());

            throw UpdateException::rollbackFailed($name->value, $e->getMessage());
        }
    }

    public function isUpdateInProgress(ModuleName $name): bool
    {
        $lockKey = self::LOCK_PREFIX.$name->value;

        return Cache::has($lockKey);
    }

    public function cancelUpdate(ModuleName $name): bool
    {
        $lockKey = self::LOCK_PREFIX.$name->value;

        return Cache::forget($lockKey);
    }

    private function createHistoryRecord(string $moduleName, string $fromVersion): ModuleUpdateHistoryModel
    {
        return ModuleUpdateHistoryModel::create([
            'id' => Str::uuid()->toString(),
            'module_name' => $moduleName,
            'from_version' => $fromVersion,
            'to_version' => 'pending',
            'status' => UpdateStatus::Pending,
            'started_at' => now(),
        ]);
    }

    private function updateStatus(ModuleUpdateHistoryModel $history, UpdateStatus $status): void
    {
        $history->updateStatus($status);
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function log(
        string $historyId,
        string $step,
        string $status,
        ?string $message = null,
        ?array $context = null
    ): void {
        ModuleUpdateLogModel::log($historyId, $step, $status, $message, $context);
    }

    private function publishPreBuiltAssets(string $moduleDir, string $moduleName): void
    {
        $sourceBuild = $moduleDir.'/public/build';

        if (! File::isDirectory($sourceBuild)) {
            return;
        }

        try {
            $targetBuild = public_path("build/modules/{$moduleName}");

            if (! File::isDirectory(dirname($targetBuild))) {
                File::makeDirectory(dirname($targetBuild), 0755, true);
            }

            if (File::isDirectory($targetBuild)) {
                File::deleteDirectory($targetBuild);
            }

            File::copyDirectory($sourceBuild, $targetBuild);

            Log::info("Published pre-built assets for module {$moduleName} after update");
        } catch (\Throwable $e) {
            Log::warning("Failed to publish pre-built assets for module {$moduleName}: {$e->getMessage()}");
        }
    }

    /**
     * @return array<string>
     */
    private function runMigrations(ModuleName $name): array
    {
        $module = $this->moduleRepository->findByName($name);
        if ($module === null) {
            return [];
        }

        $migrationPath = $module->path().'/database/migrations';
        if (! File::isDirectory($migrationPath)) {
            return [];
        }

        // Get migration files
        $files = File::files($migrationPath);
        $migrationsRun = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $migrationsRun[] = $file->getFilename();
            }
        }

        // Run migrations using Artisan
        \Artisan::call('migrate', [
            '--path' => "modules/{$name->value}/database/migrations",
            '--force' => true,
        ]);

        return $migrationsRun;
    }

    /**
     * @return array<string>
     */
    private function runNewSeeders(ModuleName $name): array
    {
        $module = $this->moduleRepository->findByName($name);
        if ($module === null) {
            return [];
        }

        $seederPath = $module->path().'/database/seeders';
        if (! File::isDirectory($seederPath)) {
            return [];
        }

        $executedSeeders = ModuleSeederHistoryModel::getExecutedSeeders($name->value);
        $seedersRun = [];

        $files = File::files($seederPath);
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $file->getFilenameWithoutExtension();
            $fullClassName = $module->namespace().'\\Database\\Seeders\\'.$className;

            if (in_array($fullClassName, $executedSeeders, true)) {
                continue;
            }

            if (! class_exists($fullClassName)) {
                // Try to load the class
                require_once $file->getPathname();
            }

            if (class_exists($fullClassName)) {
                \Artisan::call('db:seed', [
                    '--class' => $fullClassName,
                    '--force' => true,
                ]);

                ModuleSeederHistoryModel::markExecuted($name->value, $fullClassName);
                $seedersRun[] = $className;
            }
        }

        return $seedersRun;
    }
}
