<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

/**
 * Syncs the permission definitions to the database from a fresh PHP process.
 *
 * A process that just enabled a module or replaced its files still has the previous
 * set of module providers loaded, so its in-memory registry lacks the new permissions.
 */
interface PermissionSyncRunnerInterface
{
    /**
     * Never throws: a failure is logged, and the permissions:sync that runs on every
     * deploy catches up.
     */
    public function sync(): void;
}
