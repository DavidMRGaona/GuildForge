<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class MenuItemPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'menu_items.view_any');
    }

    public function view(UserModel $user, MenuItemModel $menuItem): bool
    {
        return $this->authorize($user, 'menu_items.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'menu_items.create');
    }

    public function update(UserModel $user, MenuItemModel $menuItem): bool
    {
        return $this->authorize($user, 'menu_items.update');
    }

    public function delete(UserModel $user, MenuItemModel $menuItem): bool
    {
        if ($this->isSeededByCore($menuItem)) {
            return false;
        }

        return $this->authorize($user, 'menu_items.delete');
    }

    public function restore(UserModel $user, MenuItemModel $menuItem): bool
    {
        return $this->authorize($user, 'menu_items.update');
    }

    public function forceDelete(UserModel $user, MenuItemModel $menuItem): bool
    {
        if ($this->isSeededByCore($menuItem)) {
            return false;
        }

        return $this->authorize($user, 'menu_items.delete');
    }

    /**
     * The core's own links come back with the production seeder on every deploy, so
     * deleting them would only confuse: they can be deactivated instead. A link tagged
     * with a module is an ordinary link; nothing recreates it on the module's behalf.
     */
    private function isSeededByCore(MenuItemModel $menuItem): bool
    {
        return $menuItem->module === MenuItemModel::CORE_MODULE;
    }

    public function reorder(UserModel $user): bool
    {
        return $this->authorize($user, 'menu_items.update');
    }
}
