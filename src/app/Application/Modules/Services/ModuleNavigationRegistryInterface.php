<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\NavigationItemDTO;

interface ModuleNavigationRegistryInterface
{
    /**
     * Register a navigation item from a module.
     */
    public function register(NavigationItemDTO $item): void;

    /**
     * Register multiple navigation items from a module.
     *
     * @param  array<NavigationItemDTO>  $items
     */
    public function registerMany(array $items): void;

    /**
     * Get all registered navigation items.
     *
     * @return array<NavigationItemDTO>
     */
    public function all(): array;

    /**
     * Get navigation items for a specific module.
     *
     * @return array<NavigationItemDTO>
     */
    public function forModule(string $moduleName): array;

    /**
     * Get navigation items grouped by their group name.
     *
     * @return array<string, array<NavigationItemDTO>>
     */
    public function grouped(): array;

    /**
     * Get navigation items sorted by their sort order.
     *
     * @return array<NavigationItemDTO>
     */
    public function sorted(): array;

    /**
     * Get navigation items filtered by user permissions.
     *
     * @param  array<string>  $userPermissions
     * @return array<NavigationItemDTO>
     */
    public function forUser(array $userPermissions): array;

    /**
     * Unregister all navigation items for a module.
     */
    public function unregisterModule(string $moduleName): void;

    /**
     * Clear all registered navigation items.
     */
    public function clear(): void;
}
