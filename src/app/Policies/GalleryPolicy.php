<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class GalleryPolicy
{
    public function viewAny(UserModel $user): bool
    {
        return true;
    }

    public function view(UserModel $user, GalleryModel $gallery): bool
    {
        return true;
    }

    public function create(UserModel $user): bool
    {
        return $user->canManageContent();
    }

    public function update(UserModel $user, GalleryModel $gallery): bool
    {
        return $user->canManageContent();
    }

    public function delete(UserModel $user, GalleryModel $gallery): bool
    {
        return $user->canManageContent();
    }

    public function restore(UserModel $user, GalleryModel $gallery): bool
    {
        return $user->canManageContent();
    }

    public function forceDelete(UserModel $user, GalleryModel $gallery): bool
    {
        return $user->canManageContent();
    }
}
