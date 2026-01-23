<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\DependencyCheckResultDTO;
use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\ValueObjects\ModuleName;

interface ModuleManagerServiceInterface
{
    /**
     * Discover modules in the modules directory.
     */
    public function discover(): ModuleCollection;

    /**
     * Enable a module by name.
     */
    public function enable(ModuleName $name): Module;

    /**
     * Disable a module by name.
     */
    public function disable(ModuleName $name): Module;

    /**
     * Check dependencies for a module.
     */
    public function checkDependencies(ModuleName $name): DependencyCheckResultDTO;

    /**
     * Get all modules.
     */
    public function all(): ModuleCollection;

    /**
     * Get all enabled modules.
     */
    public function enabled(): ModuleCollection;

    /**
     * Get a module by name.
     */
    public function find(ModuleName $name): ?Module;

    /**
     * Run migrations for a module.
     *
     * @return int The number of migrations run
     */
    public function migrate(ModuleName $name): int;

    /**
     * Rollback migrations for a module.
     *
     * @param int $steps Number of migrations to rollback
     * @return int The number of migrations rolled back
     */
    public function rollback(ModuleName $name, int $steps = 1): int;

    /**
     * Uninstall a module (revert migrations, delete files, remove from DB).
     *
     * @throws \App\Domain\Modules\Exceptions\ModuleNotFoundException
     * @throws \App\Domain\Modules\Exceptions\ModuleCannotUninstallException
     */
    public function uninstall(ModuleName $name): void;

    /**
     * Get settings for a module.
     *
     * @return array<string, mixed>
     */
    public function getSettings(ModuleName $name): array;

    /**
     * Update settings for a module.
     *
     * @param array<string, mixed> $settings
     */
    public function updateSettings(ModuleName $name, array $settings): void;

    /**
     * Get modules that depend on the given module.
     */
    public function getDependents(ModuleName $name): ModuleCollection;

    /**
     * Find a module entity by name (string version of find).
     */
    public function findByName(string $name): ?Module;
}
