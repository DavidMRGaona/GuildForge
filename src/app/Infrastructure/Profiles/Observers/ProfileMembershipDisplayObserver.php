<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Observers;

use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\Services\ProfileMembershipDisplayAnnouncer;

/**
 * The profile row decides two of the badge's conditions (core#824): `show_membership` and the visibility.
 * Its creation, a change of either and its deletion (the account was anonymized or deleted) announce the
 * account. The bio, the banner, the layout and the address never do.
 */
final readonly class ProfileMembershipDisplayObserver
{
    public function __construct(
        private ProfileMembershipDisplayAnnouncer $announcer,
    ) {}

    public function created(UserProfileModel $profile): void
    {
        // A row starts with the badge off: only one that starts on can change an answer
        if ($profile->show_membership) {
            $this->announcer->changed((string) $profile->getKey());
        }
    }

    public function updated(UserProfileModel $profile): void
    {
        if ($profile->wasChanged(['show_membership', 'visibility'])) {
            $this->announcer->changed((string) $profile->getKey());
        }
    }

    public function deleted(UserProfileModel $profile): void
    {
        $this->announcer->changed((string) $profile->getKey());
    }
}
