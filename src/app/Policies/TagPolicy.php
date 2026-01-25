<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Services\TagQueryServiceInterface;
use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class TagPolicy
{
    use AuthorizesWithPermissions;

    public function __construct(
        private readonly TagQueryServiceInterface $tagQueryService,
    ) {
    }

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'tags.view_any');
    }

    public function view(UserModel $user, TagModel $tag): bool
    {
        return $this->authorize($user, 'tags.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'tags.create');
    }

    public function update(UserModel $user, TagModel $tag): bool
    {
        return $this->authorize($user, 'tags.update');
    }

    public function delete(UserModel $user, TagModel $tag): bool
    {
        if (! $this->tagQueryService->canDelete($tag->id)) {
            return false;
        }

        return $this->authorize($user, 'tags.delete');
    }

    public function restore(UserModel $user, TagModel $tag): bool
    {
        return $this->authorize($user, 'tags.update');
    }

    public function forceDelete(UserModel $user, TagModel $tag): bool
    {
        if (! $this->tagQueryService->canDelete($tag->id)) {
            return false;
        }

        return $this->authorize($user, 'tags.delete');
    }
}
