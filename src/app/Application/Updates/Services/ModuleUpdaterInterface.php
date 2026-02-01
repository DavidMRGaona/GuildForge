<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Application\Updates\DTOs\ModuleUpdateResultDTO;
use App\Application\Updates\DTOs\UpdatePreviewDTO;
use App\Domain\Modules\ValueObjects\ModuleName;

/**
 * Service for applying module updates.
 */
interface ModuleUpdaterInterface
{
    /**
     * Generate a preview of what the update will do without applying it.
     */
    public function preview(ModuleName $name): UpdatePreviewDTO;

    /**
     * Apply an update to a module.
     */
    public function update(ModuleName $name): ModuleUpdateResultDTO;

    /**
     * Rollback a module to a previous backup.
     */
    public function rollback(ModuleName $name, string $backupPath): ModuleUpdateResultDTO;

    /**
     * Check if an update is currently in progress for a module.
     */
    public function isUpdateInProgress(ModuleName $name): bool;

    /**
     * Cancel an in-progress update if possible.
     */
    public function cancelUpdate(ModuleName $name): bool;
}
