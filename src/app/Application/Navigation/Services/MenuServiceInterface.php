<?php

declare(strict_types=1);

namespace App\Application\Navigation\Services;

use App\Application\Navigation\DTOs\MenuItemDTO;
use Illuminate\Contracts\Auth\Authenticatable;

interface MenuServiceInterface
{
    /**
     * Get header menu items filtered by user visibility.
     *
     * @return array<MenuItemDTO>
     */
    public function getHeaderMenu(?Authenticatable $user): array;

    /**
     * Get footer menu items filtered by user visibility.
     *
     * @return array<MenuItemDTO>
     */
    public function getFooterMenu(?Authenticatable $user): array;

    /**
     * Check if user can see a menu item based on visibility rules.
     *
     * @param  array<string>  $permissions
     */
    public function userCanSeeItem(?Authenticatable $user, string $visibility, array $permissions): bool;

    /**
     * Sync module-contributed navigation items to the database.
     * This merges module items with existing database items.
     *
     * @param  string|null  $moduleName  If provided, only sync items for this module
     */
    public function syncModuleNavigation(?string $moduleName = null): void;
}
