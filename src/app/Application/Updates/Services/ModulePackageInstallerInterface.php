<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Domain\Updates\Exceptions\UpdateException;

/**
 * Installs a module release package by staging it next to the installed module
 * and swapping directories, so a failed update can be reverted by renaming back.
 */
interface ModulePackageInstallerInterface
{
    /**
     * Extract the release ZIP into a staging directory and return the module root inside it.
     *
     * @throws UpdateException When the package is unreadable or is not exactly this module
     * @throws \App\Domain\Modules\Exceptions\ModuleIncompatibleException When the release does not fit this host (the staging copy is removed)
     */
    public function stage(string $zipPath, string $moduleName): string;

    /**
     * Replace the installed module with the staged one. Returns where the previous version was moved.
     *
     * @throws UpdateException
     */
    public function swap(string $stagedRoot, string $moduleName): string;

    /**
     * Put the previous version back in place, removing the new one.
     *
     * @throws UpdateException
     */
    public function revert(string $previousPath, string $moduleName): void;

    /**
     * Delete a staging or previous-version directory.
     */
    public function discard(string $path): void;

    /**
     * Delete staging directories left by interrupted updates of this module.
     * Previous-version copies are kept and only logged: one may be the only good version on disk.
     */
    public function cleanupLeftovers(string $moduleName): void;
}
