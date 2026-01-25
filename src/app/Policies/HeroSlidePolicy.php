<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class HeroSlidePolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'hero_slides.view_any');
    }

    public function view(UserModel $user, HeroSlideModel $heroSlide): bool
    {
        return $this->authorize($user, 'hero_slides.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'hero_slides.create');
    }

    public function update(UserModel $user, HeroSlideModel $heroSlide): bool
    {
        return $this->authorize($user, 'hero_slides.update');
    }

    public function delete(UserModel $user, HeroSlideModel $heroSlide): bool
    {
        return $this->authorize($user, 'hero_slides.delete');
    }

    public function restore(UserModel $user, HeroSlideModel $heroSlide): bool
    {
        return $this->authorize($user, 'hero_slides.update');
    }

    public function forceDelete(UserModel $user, HeroSlideModel $heroSlide): bool
    {
        return $this->authorize($user, 'hero_slides.delete');
    }

    public function reorder(UserModel $user): bool
    {
        return $this->authorize($user, 'hero_slides.update');
    }
}
