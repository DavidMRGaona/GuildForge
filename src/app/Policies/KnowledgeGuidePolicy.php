<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Knowledge\Enums\GuideSource;
use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * Help guides in the panel. Only the site's own guides are edited or deleted; official ones (core and
 * modules) come from files and are only hidden or starred (`override`).
 */
class KnowledgeGuidePolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'knowledge.manage');
    }

    public function view(UserModel $user, KnowledgeGuideModel $guide): bool
    {
        return $this->authorize($user, 'knowledge.manage');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'knowledge.manage');
    }

    public function update(UserModel $user, KnowledgeGuideModel $guide): bool
    {
        return $this->isSiteGuide($guide) && $this->authorize($user, 'knowledge.manage');
    }

    public function delete(UserModel $user, KnowledgeGuideModel $guide): bool
    {
        return $this->isSiteGuide($guide) && $this->authorize($user, 'knowledge.manage');
    }

    public function forceDelete(UserModel $user, KnowledgeGuideModel $guide): bool
    {
        return $this->delete($user, $guide);
    }

    public function restore(UserModel $user, KnowledgeGuideModel $guide): bool
    {
        return false;
    }

    /**
     * Hide or star an official guide on this site.
     */
    public function override(UserModel $user, KnowledgeGuideModel $guide): bool
    {
        return ! $this->isSiteGuide($guide) && $this->authorize($user, 'knowledge.manage');
    }

    private function isSiteGuide(KnowledgeGuideModel $guide): bool
    {
        return $guide->source === GuideSource::Site->value;
    }
}
