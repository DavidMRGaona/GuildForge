<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class UserPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'users.view_any');
    }

    public function view(UserModel $user, UserModel $model): bool
    {
        return $this->authorize($user, 'users.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'users.create');
    }

    public function update(UserModel $user, UserModel $model): bool
    {
        return $this->authorize($user, 'users.update') && $this->canManage($user, $model);
    }

    public function delete(UserModel $user, UserModel $model): bool
    {
        return $this->authorize($user, 'users.delete') && $user->id !== $model->id && $this->canManage($user, $model);
    }

    public function restore(UserModel $user, UserModel $model): bool
    {
        return $this->authorize($user, 'users.delete') && $this->canManage($user, $model);
    }

    public function forceDelete(UserModel $user, UserModel $model): bool
    {
        return $this->authorize($user, 'users.delete') && $user->id !== $model->id && $this->canManage($user, $model);
    }

    private function canManage(UserModel $user, UserModel $model): bool
    {
        return app(RoleGrantPolicy::class)->canManageAccount($user, $model);
    }
}
