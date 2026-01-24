<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\PermissionDTO;

interface ModulePermissionRegistryInterface
{
    /**
     * Register a permission from a module.
     */
    public function register(PermissionDTO $permission): void;

    /**
     * Register multiple permissions from a module.
     *
     * @param  array<PermissionDTO>  $permissions
     */
    public function registerMany(array $permissions): void;

    /**
     * Get all registered permissions.
     *
     * @return array<PermissionDTO>
     */
    public function all(): array;

    /**
     * Get permissions for a specific module.
     *
     * @return array<PermissionDTO>
     */
    public function forModule(string $moduleName): array;

    /**
     * Get permissions grouped by their group name.
     *
     * @return array<string, array<PermissionDTO>>
     */
    public function grouped(): array;

    /**
     * Find a permission by its full name.
     */
    public function find(string $name): ?PermissionDTO;

    /**
     * Check if a permission is registered.
     */
    public function has(string $name): bool;

    /**
     * Unregister all permissions for a module.
     */
    public function unregisterModule(string $moduleName): void;

    /**
     * Clear all registered permissions.
     */
    public function clear(): void;
}
