<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Observers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Profiles\Services\ProfileMembershipDisplayAnnouncer;

/**
 * The account decides the rest of the badge's conditions (core#824): its public name, and whether it is
 * active. A new public name, anonymizing, deactivating, restoring and deleting for good announce it.
 */
final readonly class AccountMembershipDisplayObserver
{
    public function __construct(
        private ProfileMembershipDisplayAnnouncer $announcer,
    ) {}

    public function updated(UserModel $user): void
    {
        if ($user->wasChanged(['display_name', 'anonymized_at'])) {
            $this->announcer->changed((string) $user->getKey());
        }
    }

    /** Deactivating (soft delete) and deleting for good */
    public function deleted(UserModel $user): void
    {
        $this->announcer->changed((string) $user->getKey());
    }

    public function restored(UserModel $user): void
    {
        $this->announcer->changed((string) $user->getKey());
    }
}
