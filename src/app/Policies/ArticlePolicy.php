<?php

declare(strict_types=1);

namespace App\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class ArticlePolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'articles.view_any');
    }

    public function view(UserModel $user, ArticleModel $article): bool
    {
        return $this->authorize($user, 'articles.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'articles.create');
    }

    public function update(UserModel $user, ArticleModel $article): bool
    {
        return $this->authorize($user, 'articles.update');
    }

    public function delete(UserModel $user, ArticleModel $article): bool
    {
        return $this->authorize($user, 'articles.delete');
    }

    public function restore(UserModel $user, ArticleModel $article): bool
    {
        return $this->authorize($user, 'articles.update');
    }

    public function forceDelete(UserModel $user, ArticleModel $article): bool
    {
        return $this->authorize($user, 'articles.delete');
    }
}
