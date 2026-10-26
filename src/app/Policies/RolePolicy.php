<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Authorization\Services\AssociationMemberRoleRule;
use App\Application\Authorization\Services\BaseRoleRule;
use App\Application\Authorization\Services\RoleGrantPolicy;
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
        if (! $this->authorize($user, 'roles.update')) {
            return false;
        }

        // Whoever is not an admin cannot open a role that goes beyond their own permissions
        return app(RoleGrantPolicy::class)->canManageRole($user, $role->name);
    }

    public function delete(UserModel $user, RoleModel $role): bool
    {
        // Cannot delete protected roles, the base role of every account, nor the role modules
        // use to find association members
        if ($this->isUndeletable($role)) {
            return false;
        }

        return $this->authorize($user, 'roles.delete')
            && app(RoleGrantPolicy::class)->canManageRole($user, $role->name);
    }

    public function restore(UserModel $user, RoleModel $role): bool
    {
        return $this->authorize($user, 'roles.update');
    }

    public function forceDelete(UserModel $user, RoleModel $role): bool
    {
        if ($this->isUndeletable($role)) {
            return false;
        }

        return $this->authorize($user, 'roles.delete')
            && app(RoleGrantPolicy::class)->canManageRole($user, $role->name);
    }

    private function isUndeletable(RoleModel $role): bool
    {
        return $role->is_protected
            || app(BaseRoleRule::class)->isBaseRoleName($role->name)
            || app(AssociationMemberRoleRule::class)->isAssociationMemberRoleName($role->name);
    }
}
