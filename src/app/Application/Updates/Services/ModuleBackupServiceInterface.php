<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Support\Collection;

/**
 * Service for creating and managing module backups.
 */
interface ModuleBackupServiceInterface
{
    /**
     * Create a backup of a module.
     *
     * @return string Path to the backup file
     */
    public function createBackup(ModuleName $name): string;

    /**
     * Restore a module from a backup.
     */
    public function restoreBackup(ModuleName $name, string $backupPath): void;

    /**
     * Get all available backups for a module.
     *
     * @return Collection<int, array{path: string, created_at: \DateTimeImmutable, size: int, version: string}>
     */
    public function listBackups(ModuleName $name): Collection;

    /**
     * Delete a specific backup.
     */
    public function deleteBackup(string $backupPath): void;

    /**
     * Clean up old backups, keeping only the configured retention count.
     */
    public function cleanupOldBackups(ModuleName $name): int;

    /**
     * Get the total backup size for a module.
     */
    public function getBackupSize(ModuleName $name): int;
}
