<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class EventPolicy
{
    public function viewAny(UserModel $user): bool
    {
        return true;
    }

    public function view(UserModel $user, EventModel $event): bool
    {
        return true;
    }

    public function create(UserModel $user): bool
    {
        return $user->canManageContent();
    }

    public function update(UserModel $user, EventModel $event): bool
    {
        return $user->canManageContent();
    }

    public function delete(UserModel $user, EventModel $event): bool
    {
        return $user->canManageContent();
    }

    public function restore(UserModel $user, EventModel $event): bool
    {
        return $user->canManageContent();
    }

    public function forceDelete(UserModel $user, EventModel $event): bool
    {
        return $user->canManageContent();
    }
}
