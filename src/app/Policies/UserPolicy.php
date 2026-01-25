<?php

declare(strict_types=1);

namespace App\Policies;

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
        return $this->authorize($user, 'users.update');
    }

    public function delete(UserModel $user, UserModel $model): bool
    {
        return $this->authorize($user, 'users.delete') && $user->id !== $model->id;
    }

    public function restore(UserModel $user, UserModel $model): bool
    {
        return $this->authorize($user, 'users.update');
    }

    public function forceDelete(UserModel $user, UserModel $model): bool
    {
        return $this->authorize($user, 'users.delete') && $user->id !== $model->id;
    }
}
