<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;

interface PermissionRegistryInterface
{
    /**
     * Register a single permission definition.
     */
    public function register(PermissionDefinitionDTO $permission): void;

    /**
     * Register multiple permission definitions.
     *
     * @param  array<PermissionDefinitionDTO>  $permissions
     */
    public function registerMany(array $permissions): void;

    /**
     * Get all registered permission definitions.
     *
     * @return array<PermissionDefinitionDTO>
     */
    public function all(): array;

    /**
     * Find a permission definition by key.
     */
    public function find(string $key): ?PermissionDefinitionDTO;

    /**
     * Check if a permission key is registered.
     */
    public function has(string $key): bool;

    /**
     * Get permissions for a specific resource.
     *
     * @return array<PermissionDefinitionDTO>
     */
    public function forResource(string $resource): array;

    /**
     * Get permissions for a specific module.
     *
     * @return array<PermissionDefinitionDTO>
     */
    public function forModule(string $module): array;

    /**
     * Get permissions grouped by resource.
     *
     * @return array<string, array<PermissionDefinitionDTO>>
     */
    public function grouped(): array;

    /**
     * Unregister all permissions for a module.
     */
    public function unregisterModule(string $module): void;

    /**
     * Sync registered permissions to database.
     */
    public function syncToDatabase(): void;

    /**
     * Clear all registered permissions.
     */
    public function clear(): void;
}
