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

    /**
     * «Restablecer perfil público» (core#746): only with the permission, and only for an account the person
     * could edit, so nobody moderates the profile of somebody holding more than they do.
     */
    public function resetPublicProfile(UserModel $user, UserModel $model): bool
    {
        return $this->authorize($user, 'profiles.moderate') && $this->canManage($user, $model);
    }

    /**
     * «Restablecer el doble factor» (core#771): only with `users.reset_two_factor`, which only the
     * administrator role can hold, and on an account the person could edit. Never one's own (that is
     * «Seguridad» in the account settings: otherwise a stolen session could drop the second factor)
     * and never an anonymized one.
     */
    public function resetTwoFactor(UserModel $user, UserModel $model): bool
    {
        return $this->authorize($user, 'users.reset_two_factor')
            && $user->id !== $model->id
            && $model->anonymized_at === null
            && $this->canManage($user, $model);
    }

    private function canManage(UserModel $user, UserModel $model): bool
    {
        return app(RoleGrantPolicy::class)->canManageAccount($user, $model);
    }
}
