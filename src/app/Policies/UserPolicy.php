<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class UserPolicy
{
    public function viewAny(UserModel $user): bool
    {
        return $user->canManageUsers();
    }

    public function view(UserModel $user, UserModel $model): bool
    {
        return $user->canManageUsers();
    }

    public function create(UserModel $user): bool
    {
        return $user->canManageUsers();
    }

    public function update(UserModel $user, UserModel $model): bool
    {
        return $user->canManageUsers();
    }

    public function delete(UserModel $user, UserModel $model): bool
    {
        return $user->canManageUsers() && $user->id !== $model->id;
    }

    public function restore(UserModel $user, UserModel $model): bool
    {
        return $user->canManageUsers();
    }

    public function forceDelete(UserModel $user, UserModel $model): bool
    {
        return $user->canManageUsers() && $user->id !== $model->id;
    }
}
