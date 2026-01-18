<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class ArticlePolicy
{
    public function viewAny(UserModel $user): bool
    {
        return true;
    }

    public function view(UserModel $user, ArticleModel $article): bool
    {
        return true;
    }

    public function create(UserModel $user): bool
    {
        return $user->canManageContent();
    }

    public function update(UserModel $user, ArticleModel $article): bool
    {
        return $user->canManageContent();
    }

    public function delete(UserModel $user, ArticleModel $article): bool
    {
        return $user->canManageContent();
    }

    public function restore(UserModel $user, ArticleModel $article): bool
    {
        return $user->canManageContent();
    }

    public function forceDelete(UserModel $user, ArticleModel $article): bool
    {
        return $user->canManageContent();
    }
}
