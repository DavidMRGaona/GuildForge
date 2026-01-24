<?php

declare(strict_types=1);

namespace App\Modules\Traits;

use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\Services\ModuleNavigationRegistryInterface;
use App\Application\Modules\Services\ModulePermissionRegistryInterface;

/**
 * Trait for services that need to interact with the module system.
 *
 * Use this trait in services that need to query or register
 * module permissions and navigation items.
 */
trait InteractsWithModules
{
    /**
     * Get the permission registry.
     */
    protected function permissionRegistry(): ModulePermissionRegistryInterface
    {
        return app(ModulePermissionRegistryInterface::class);
    }

    /**
     * Get the navigation registry.
     */
    protected function navigationRegistry(): ModuleNavigationRegistryInterface
    {
        return app(ModuleNavigationRegistryInterface::class);
    }

    /**
     * Register a permission from a module.
     */
    protected function registerPermission(PermissionDTO $permission): void
    {
        $this->permissionRegistry()->register($permission);
    }

    /**
     * Register multiple permissions from a module.
     *
     * @param  array<PermissionDTO>  $permissions
     */
    protected function registerPermissions(array $permissions): void
    {
        $this->permissionRegistry()->registerMany($permissions);
    }

    /**
     * Register a navigation item from a module.
     */
    protected function registerNavigation(NavigationItemDTO $item): void
    {
        $this->navigationRegistry()->register($item);
    }

    /**
     * Register multiple navigation items from a module.
     *
     * @param  array<NavigationItemDTO>  $items
     */
    protected function registerNavigationItems(array $items): void
    {
        $this->navigationRegistry()->registerMany($items);
    }

    /**
     * Get all registered permissions.
     *
     * @return array<PermissionDTO>
     */
    protected function getAllPermissions(): array
    {
        return $this->permissionRegistry()->all();
    }

    /**
     * Get permissions grouped by group name.
     *
     * @return array<string, array<PermissionDTO>>
     */
    protected function getGroupedPermissions(): array
    {
        return $this->permissionRegistry()->grouped();
    }

    /**
     * Get all navigation items sorted by sort order.
     *
     * @return array<NavigationItemDTO>
     */
    protected function getSortedNavigation(): array
    {
        return $this->navigationRegistry()->sorted();
    }

    /**
     * Get navigation items for a user based on their permissions.
     *
     * @param  array<string>  $userPermissions
     * @return array<NavigationItemDTO>
     */
    protected function getNavigationForUser(array $userPermissions): array
    {
        return $this->navigationRegistry()->forUser($userPermissions);
    }

    /**
     * Check if a permission exists.
     */
    protected function hasPermission(string $name): bool
    {
        return $this->permissionRegistry()->has($name);
    }
}
