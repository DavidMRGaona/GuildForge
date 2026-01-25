<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class EventPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'events.view_any');
    }

    public function view(UserModel $user, EventModel $event): bool
    {
        return $this->authorize($user, 'events.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'events.create');
    }

    public function update(UserModel $user, EventModel $event): bool
    {
        return $this->authorize($user, 'events.update');
    }

    public function delete(UserModel $user, EventModel $event): bool
    {
        return $this->authorize($user, 'events.delete');
    }

    public function restore(UserModel $user, EventModel $event): bool
    {
        return $this->authorize($user, 'events.update');
    }

    public function forceDelete(UserModel $user, EventModel $event): bool
    {
        return $this->authorize($user, 'events.delete');
    }
}
