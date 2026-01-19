<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class HeroSlidePolicy
{
    public function viewAny(UserModel $user): bool
    {
        return true;
    }

    public function view(UserModel $user, HeroSlideModel $heroSlide): bool
    {
        return true;
    }

    public function create(UserModel $user): bool
    {
        return $user->canManageContent();
    }

    public function update(UserModel $user, HeroSlideModel $heroSlide): bool
    {
        return $user->canManageContent();
    }

    public function delete(UserModel $user, HeroSlideModel $heroSlide): bool
    {
        return $user->canManageContent();
    }

    public function restore(UserModel $user, HeroSlideModel $heroSlide): bool
    {
        return $user->canManageContent();
    }

    public function forceDelete(UserModel $user, HeroSlideModel $heroSlide): bool
    {
        return $user->canManageContent();
    }

    public function reorder(UserModel $user): bool
    {
        return $user->canManageContent();
    }
}
