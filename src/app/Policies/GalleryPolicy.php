<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class GalleryPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'galleries.view_any');
    }

    public function view(UserModel $user, GalleryModel $gallery): bool
    {
        return $this->authorize($user, 'galleries.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'galleries.create');
    }

    public function update(UserModel $user, GalleryModel $gallery): bool
    {
        return $this->authorize($user, 'galleries.update');
    }

    public function delete(UserModel $user, GalleryModel $gallery): bool
    {
        return $this->authorize($user, 'galleries.delete');
    }

    public function restore(UserModel $user, GalleryModel $gallery): bool
    {
        return $this->authorize($user, 'galleries.update');
    }

    public function forceDelete(UserModel $user, GalleryModel $gallery): bool
    {
        return $this->authorize($user, 'galleries.delete');
    }
}
