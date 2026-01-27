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
        // Don't allow deletion of module-contributed items
        if ($menuItem->module !== null) {
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
        if ($menuItem->module !== null) {
            return false;
        }

        return $this->authorize($user, 'menu_items.delete');
    }

    public function reorder(UserModel $user): bool
    {
        return $this->authorize($user, 'menu_items.update');
    }
}
