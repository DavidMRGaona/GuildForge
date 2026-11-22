<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Domain\Modules\Exceptions\ModuleSettingsUnavailableException;

/**
 * Publishes each booted module's settings under config('modules.settings.<name>') and keeps
 * them current in long-running processes (core#825). Bound as a singleton: one per process.
 */
interface ModuleSettingsConfigInterface
{
    /**
     * Publish a module's settings while it boots: release defaults with the stored values on
     * top. Never throws: a store that cannot be read gives the defaults, and the next refresh()
     * reads every module again strictly.
     */
    public function publish(string $moduleName, string $modulePath): void;

    /**
     * Publish every booted module's settings again when any setting was saved since the last
     * time. A read that fails is tried once more after a short pause; if it fails again it
     * throws, and the next call reads everything again.
     *
     * @throws ModuleSettingsUnavailableException
     */
    public function refresh(): void;
}
