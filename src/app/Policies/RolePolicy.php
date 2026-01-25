<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class RolePolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'roles.view_any');
    }

    public function view(UserModel $user, RoleModel $role): bool
    {
        return $this->authorize($user, 'roles.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'roles.create');
    }

    public function update(UserModel $user, RoleModel $role): bool
    {
        return $this->authorize($user, 'roles.update');
    }

    public function delete(UserModel $user, RoleModel $role): bool
    {
        // Cannot delete protected roles
        if ($role->is_protected) {
            return false;
        }

        return $this->authorize($user, 'roles.delete');
    }

    public function restore(UserModel $user, RoleModel $role): bool
    {
        return $this->authorize($user, 'roles.update');
    }

    public function forceDelete(UserModel $user, RoleModel $role): bool
    {
        if ($role->is_protected) {
            return false;
        }

        return $this->authorize($user, 'roles.delete');
    }
}
