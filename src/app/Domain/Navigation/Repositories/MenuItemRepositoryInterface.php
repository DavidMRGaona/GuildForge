<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Repositories;

use App\Domain\Navigation\Entities\MenuItem;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\ValueObjects\MenuItemId;

interface MenuItemRepositoryInterface
{
    public function findById(MenuItemId $id): ?MenuItem;

    /**
     * Get all menu items for a location, sorted by sort_order.
     *
     * @return array<MenuItem>
     */
    public function findByLocation(MenuLocation $location): array;

    /**
     * Get root menu items for a location (items without parent).
     *
     * @return array<MenuItem>
     */
    public function findRootsByLocation(MenuLocation $location): array;

    /**
     * Get children of a menu item.
     *
     * @return array<MenuItem>
     */
    public function findChildren(MenuItemId $parentId): array;

    /**
     * Get all active menu items for a location with their children nested.
     *
     * @return array<MenuItem>
     */
    public function findActiveByLocationWithChildren(MenuLocation $location): array;

    /**
     * Get all menu items.
     *
     * @return array<MenuItem>
     */
    public function all(): array;

    /**
     * Get all active menu items.
     *
     * @return array<MenuItem>
     */
    public function allActive(): array;

    public function save(MenuItem $menuItem): void;

    public function delete(MenuItem $menuItem): void;

    /**
     * Delete all menu items from a module.
     */
    public function deleteByModule(string $module): void;

    /**
     * Get the maximum sort order for a location.
     */
    public function maxSortOrder(MenuLocation $location, ?MenuItemId $parentId = null): int;
}
