<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Updates\Services\ModuleBackupServiceInterface;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Updates\Exceptions\UpdateException;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

final readonly class ModuleBackupService implements ModuleBackupServiceInterface
{
    public function __construct(
        private ModuleManagerServiceInterface $moduleManager,
    ) {
    }

    public function createBackup(ModuleName $name): string
    {
        $module = $this->moduleManager->find($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        $modulePath = $module->path();
        if (! File::isDirectory($modulePath)) {
            throw UpdateException::backupFailed($name->value, 'Module directory does not exist');
        }

        $backupPath = $this->getBackupDirectory($name->value);
        $this->ensureDirectoryExists($backupPath);

        $timestamp = date('Y-m-d_H-i-s');
        $version = $module->version()->value();
        $backupFile = "{$backupPath}/{$name->value}_{$version}_{$timestamp}.zip";

        try {
            $this->createZipBackup($modulePath, $backupFile);
            $this->cleanupOldBackups($name);

            Log::info("Created backup for module {$name->value}", [
                'backup_path' => $backupFile,
                'version' => $version,
            ]);

            return $backupFile;
        } catch (\Throwable $e) {
            if (File::exists($backupFile)) {
                File::delete($backupFile);
            }

            throw UpdateException::backupFailed($name->value, $e->getMessage());
        }
    }

    public function restoreBackup(ModuleName $name, string $backupPath): void
    {
        if (! File::exists($backupPath)) {
            throw UpdateException::rollbackFailed($name->value, "Backup file not found: {$backupPath}");
        }

        $module = $this->moduleManager->find($name);
        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        $modulePath = $module->path();

        try {
            // Remove current module directory
            if (File::isDirectory($modulePath)) {
                File::deleteDirectory($modulePath);
            }

            // Extract backup
            $this->extractZipBackup($backupPath, dirname($modulePath));

            Log::info("Restored module {$name->value} from backup", [
                'backup_path' => $backupPath,
            ]);
        } catch (\Throwable $e) {
            throw UpdateException::rollbackFailed($name->value, $e->getMessage());
        }
    }

    public function listBackups(ModuleName $name): Collection
    {
        $backupPath = $this->getBackupDirectory($name->value);

        if (! File::isDirectory($backupPath)) {
            return new Collection();
        }

        $files = File::files($backupPath);
        $backups = new Collection();

        foreach ($files as $file) {
            if ($file->getExtension() !== 'zip') {
                continue;
            }

            $filename = $file->getFilenameWithoutExtension();
            // Parse filename: {module}_{version}_{timestamp}
            $parts = explode('_', $filename);
            $version = $parts[1] ?? 'unknown';

            $backups->push([
                'path' => $file->getPathname(),
                'created_at' => new DateTimeImmutable('@' . $file->getMTime()),
                'size' => $file->getSize(),
                'version' => $version,
            ]);
        }

        return $backups->sortByDesc('created_at')->values();
    }

    public function deleteBackup(string $backupPath): void
    {
        if (File::exists($backupPath)) {
            File::delete($backupPath);
        }
    }

    public function cleanupOldBackups(ModuleName $name): int
    {
        $retention = config('updates.backup_retention', 5);
        $backups = $this->listBackups($name);

        if ($backups->count() <= $retention) {
            return 0;
        }

        $toDelete = $backups->slice($retention);
        $deleted = 0;

        foreach ($toDelete as $backup) {
            $this->deleteBackup($backup['path']);
            $deleted++;
        }

        return $deleted;
    }

    public function getBackupSize(ModuleName $name): int
    {
        $backups = $this->listBackups($name);

        return $backups->sum('size');
    }

    private function getBackupDirectory(string $moduleName): string
    {
        $basePath = config('updates.backup_path', storage_path('app/backups'));

        return "{$basePath}/modules/{$moduleName}";
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    private function createZipBackup(string $sourcePath, string $destinationPath): void
    {
        $zip = new ZipArchive();

        if ($zip->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create ZIP file: {$destinationPath}");
        }

        $realSourcePath = realpath($sourcePath);

        if ($realSourcePath === false) {
            throw new \RuntimeException("Source path does not exist: {$sourcePath}");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realSourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();

                if ($filePath !== false) {
                    $relativePath = substr($filePath, strlen($realSourcePath) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        $zip->close();
    }

    private function extractZipBackup(string $zipPath, string $destinationPath): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Cannot open ZIP file: {$zipPath}");
        }

        $zip->extractTo($destinationPath);
        $zip->close();
    }
}
