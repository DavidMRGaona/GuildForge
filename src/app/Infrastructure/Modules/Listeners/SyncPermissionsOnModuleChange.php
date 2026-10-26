<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Listeners;

use App\Application\Authorization\Services\PermissionSyncRunnerInterface;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUpdated;

/**
 * Writes the permissions of a module that was just enabled or updated from a ZIP to the
 * database, so the role editor lists them right away instead of after the next deploy.
 *
 * Updates from the panel sync inside module:finish-update instead.
 */
final readonly class SyncPermissionsOnModuleChange
{
    public function __construct(
        private PermissionSyncRunnerInterface $permissionSync,
    ) {}

    public function handle(ModuleEnabled|ModuleUpdated $event): void
    {
        $this->permissionSync->sync();
    }
}
