<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Application\Modules\Services\ModuleInstallerInterface;
use App\Application\Updates\Services\ModuleBackupServiceInterface;
use App\Domain\Modules\Events\ModuleInstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Modules\Exceptions\ModuleInstallationException;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use ZipArchive;

final readonly class ModuleInstaller implements ModuleInstallerInterface
{
    private const string TEMP_DIRECTORY = 'temp/modules';

    public function __construct(
        private Dispatcher $events,
        private ModuleRepositoryInterface $repository,
        private ModuleBackupServiceInterface $backupService,
        private ModuleMigrationRunner $migrationRunner,
        private ModuleSeederRunner $seederRunner,
    ) {}

    public function installFromZip(UploadedFile $file): ModuleManifestDTO
    {
        $this->validateZipFile($file);

        $tempPath = $this->extractToTemp($file);

        try {
            $manifest = $this->findAndValidateManifest($tempPath);
            $this->validateModuleNameForInstall($manifest);

            $targetPath = $this->moveToModulesDirectory($tempPath, $manifest->name);
            $this->publishPreBuiltAssets($targetPath, $manifest->name);

            $this->events->dispatch(new ModuleInstalled(
                $manifest->name,
                $manifest->version,
                $targetPath,
            ));

            return $manifest;
        } finally {
            $this->cleanupTempDirectory($tempPath);
        }
    }

    public function updateFromZip(UploadedFile $file): ModuleManifestDTO
    {
        $this->validateZipFile($file);

        $tempPath = $this->extractToTemp($file);

        try {
            $manifest = $this->findAndValidateManifest($tempPath);
            $moduleName = new ModuleName($manifest->name);

            $module = $this->repository->findByName($moduleName);

            if ($module === null) {
                throw ModuleInstallationException::moduleNotInstalled($manifest->name);
            }

            $previousVersion = $module->version()->value();

            // Create backup before updating
            $backupPath = $this->backupService->createBackup($moduleName);
            Log::info("Created backup for module {$manifest->name} before update", [
                'backup_path' => $backupPath,
            ]);

            try {
                // Remove current module directory
                $modulesPath = config('modules.path', base_path('modules'));
                $targetPath = $modulesPath.'/'.$manifest->name;

                if (File::isDirectory($targetPath)) {
                    File::deleteDirectory($targetPath);
                }

                // Move new files into place
                $targetPath = $this->moveToModulesDirectory($tempPath, $manifest->name);
                $this->publishPreBuiltAssets($targetPath, $manifest->name);

                // Update version in DB
                $module->updateVersion(ModuleVersion::fromString($manifest->version));
                $this->repository->save($module);

                // Run migrations and seeders
                $this->migrationRunner->run($module);
                $this->seederRunner->run($module);

                $this->events->dispatch(new ModuleUpdated(
                    $manifest->name,
                    $previousVersion,
                    $manifest->version,
                    $targetPath,
                ));

                return $manifest;
            } catch (\Throwable $e) {
                // Restore from backup on failure
                Log::error("Module update failed for {$manifest->name}, restoring backup", [
                    'error' => $e->getMessage(),
                ]);

                try {
                    $this->backupService->restoreBackup($moduleName, $backupPath);
                    // Restore original version in DB
                    $module->updateVersion(ModuleVersion::fromString($previousVersion));
                    $this->repository->save($module);
                } catch (\Throwable $restoreError) {
                    Log::critical("Failed to restore backup for {$manifest->name}", [
                        'error' => $restoreError->getMessage(),
                    ]);
                }

                throw ModuleInstallationException::updateFailed($e->getMessage());
            }
        } finally {
            $this->cleanupTempDirectory($tempPath);
        }
    }

    /**
     * Check if a module exists (by name in DB) for use by the install/update smart action.
     */
    public function moduleExists(string $name): bool
    {
        try {
            $moduleName = new ModuleName($name);

            return $this->repository->exists($moduleName);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Extract the manifest from a ZIP file without installing it.
     */
    public function peekManifest(UploadedFile $file): ModuleManifestDTO
    {
        $this->validateZipFile($file);

        $tempPath = $this->extractToTemp($file);

        try {
            return $this->findAndValidateManifest($tempPath);
        } finally {
            $this->cleanupTempDirectory($tempPath);
        }
    }

    private function validateZipFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_ZIP_SIZE) {
            throw ModuleInstallationException::zipTooLarge((int) (self::MAX_ZIP_SIZE / (1024 * 1024)));
        }

        // Validate by actually opening as ZIP (more reliable than extension check)
        $zip = new ZipArchive;
        $result = $zip->open($file->getPathname());

        if ($result !== true) {
            throw ModuleInstallationException::invalidZip();
        }

        $zip->close();
    }

    private function extractToTemp(UploadedFile $file): string
    {
        $tempId = uniqid('module_', true);
        $tempPath = storage_path('app/'.self::TEMP_DIRECTORY.'/'.$tempId);

        if (! File::makeDirectory($tempPath, 0755, true)) {
            throw ModuleInstallationException::extractionFailed('Failed to create temp directory');
        }

        $zip = new ZipArchive;
        $zip->open($file->getPathname());
        $zip->extractTo($tempPath);
        $zip->close();

        return $tempPath;
    }

    private function findAndValidateManifest(string $tempPath): ModuleManifestDTO
    {
        $manifestPath = $this->findManifestPath($tempPath);

        if ($manifestPath === null) {
            throw ModuleInstallationException::manifestNotFound();
        }

        $content = File::get($manifestPath);
        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw ModuleInstallationException::invalidManifestJson();
        }

        foreach (self::REQUIRED_MANIFEST_FIELDS as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                throw ModuleInstallationException::missingManifestField($field);
            }
        }

        try {
            return ModuleManifestDTO::fromArray($data);
        } catch (InvalidArgumentException $e) {
            throw ModuleInstallationException::invalidManifestJson();
        }
    }

    private function findManifestPath(string $tempPath): ?string
    {
        $rootManifest = $tempPath.'/module.json';
        if (File::exists($rootManifest)) {
            return $rootManifest;
        }

        $directories = File::directories($tempPath);
        if (count($directories) === 1) {
            $subdirManifest = $directories[0].'/module.json';
            if (File::exists($subdirManifest)) {
                return $subdirManifest;
            }
        }

        return null;
    }

    private function validateModuleNameForInstall(ModuleManifestDTO $manifest): void
    {
        $name = strtolower($manifest->name);

        if (in_array($name, self::FORBIDDEN_NAMES, true)) {
            throw ModuleInstallationException::forbiddenModuleName($manifest->name);
        }

        $modulesPath = config('modules.path', base_path('modules'));
        $targetPath = $modulesPath.'/'.$manifest->name;

        if (File::isDirectory($targetPath)) {
            $moduleName = new ModuleName($manifest->name);

            if ($this->repository->exists($moduleName)) {
                throw ModuleInstallationException::moduleAlreadyExists($manifest->name);
            }

            // Leftover directory from incomplete uninstall — clean it up
            Log::warning("Removing leftover module directory for '{$manifest->name}' (no DB record)");
            if (! File::deleteDirectory($targetPath)) {
                throw ModuleInstallationException::extractionFailed(
                    "Failed to clean up leftover directory for module '{$manifest->name}'"
                );
            }
        }
    }

    private function moveToModulesDirectory(string $tempPath, string $moduleName): string
    {
        $modulesPath = config('modules.path', base_path('modules'));
        $targetPath = $modulesPath.'/'.$moduleName;

        if (! File::isDirectory($modulesPath) && ! File::makeDirectory($modulesPath, 0755, true)) {
            throw ModuleInstallationException::extractionFailed('Failed to create modules directory');
        }

        $sourcePath = $this->getSourcePath($tempPath);

        // Try rename first (fast, same-filesystem), fall back to copy+delete (cross-filesystem/Docker)
        if (! File::moveDirectory($sourcePath, $targetPath)) {
            if (! File::copyDirectory($sourcePath, $targetPath)) {
                throw ModuleInstallationException::extractionFailed('Failed to move module to target directory');
            }
            File::deleteDirectory($sourcePath);
        }

        return $targetPath;
    }

    private function getSourcePath(string $tempPath): string
    {
        if (File::exists($tempPath.'/module.json')) {
            return $tempPath;
        }

        $directories = File::directories($tempPath);
        if (count($directories) === 1 && File::exists($directories[0].'/module.json')) {
            return $directories[0];
        }

        return $tempPath;
    }

    /**
     * Copy pre-built assets from the module directory to the public build directory.
     * Release ZIPs include compiled assets in public/build/ — these need to be placed
     * where the app expects them: public/build/modules/{name}/
     */
    private function publishPreBuiltAssets(string $moduleDir, string $moduleName): void
    {
        $sourceBuild = $moduleDir.'/public/build';

        if (! File::isDirectory($sourceBuild)) {
            Log::info("No pre-built assets found for module {$moduleName}");

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
            File::deleteDirectory($sourceBuild);

            Log::info("Published pre-built assets for module {$moduleName}");
        } catch (\Throwable $e) {
            Log::warning("Failed to publish pre-built assets for module {$moduleName}: {$e->getMessage()}");
        }
    }

    private function cleanupTempDirectory(string $tempPath): void
    {
        if (File::isDirectory($tempPath)) {
            File::deleteDirectory($tempPath);
        }
    }
}
