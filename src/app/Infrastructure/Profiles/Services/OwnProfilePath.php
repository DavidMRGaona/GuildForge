<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Profiles\Services\ProfileAccessRule;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\StoredProfiles;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The path of a signed-in person's own public profile (core#745), for the user menu and the account
 * settings page. It comes from the profile row, never from the account name or email, and goes through
 * ProfileAccessRule like any other way into a profile: an account without a row, deactivated or
 * anonymized has no link. The owner always passes the rule otherwise, even with a hidden profile or
 * without a public name (the profile page tells them nobody else sees it).
 *
 * A class of its own rather than a method of PublicProfileServiceInterface: modules implement that
 * interface in their tests, and a new method would break them.
 */
final readonly class OwnProfilePath
{
    public function __construct(
        private StoredProfiles $rows,
        private ProfileAccessRule $access,
    ) {}

    public function for(?Authenticatable $user): ?string
    {
        if (! $user instanceof UserModel) {
            return null;
        }

        $row = $this->rows->find((string) $user->getAuthIdentifier());

        if (! $row instanceof UserProfileModel) {
            return null;
        }

        $profile = PublicProfileService::describe($row, $user);

        return $this->access->canView($profile, $user)
            ? route('profiles.show', ['handle' => $row->handle], absolute: false)
            : null;
    }
}
