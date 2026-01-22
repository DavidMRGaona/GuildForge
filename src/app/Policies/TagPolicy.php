<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Services\TagQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class TagPolicy
{
    public function __construct(
        private readonly TagQueryServiceInterface $tagQueryService,
    ) {
    }

    public function viewAny(UserModel $user): bool
    {
        return true;
    }

    public function view(UserModel $user, TagModel $tag): bool
    {
        return true;
    }

    public function create(UserModel $user): bool
    {
        return $user->canManageContent();
    }

    public function update(UserModel $user, TagModel $tag): bool
    {
        return $user->canManageContent();
    }

    public function delete(UserModel $user, TagModel $tag): bool
    {
        if (!$this->tagQueryService->canDelete($tag->id)) {
            return false;
        }

        return $user->canManageContent();
    }

    public function restore(UserModel $user, TagModel $tag): bool
    {
        return $user->canManageContent();
    }

    public function forceDelete(UserModel $user, TagModel $tag): bool
    {
        if (!$this->tagQueryService->canDelete($tag->id)) {
            return false;
        }

        return $user->canManageContent();
    }
}
