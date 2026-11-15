<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileCardDTO;
use App\Application\Profiles\DTOs\ProfileLookupDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Reading public profiles for a viewer (core#741). Every answer has already gone through
 * ProfileAccessRule: nothing about a profile the viewer may not see ever leaves it.
 */
interface PublicProfileServiceInterface
{
    /**
     * The profile at this address for this viewer. An old address of the profile, or the current one
     * written differently, gives the current address, but only once the viewer may see the profile:
     * otherwise it is not found, like an unknown, malformed or reserved address.
     */
    public function findByHandle(string $handle, ?Authenticatable $viewer): ProfileLookupDTO;

    /**
     * The summary card of an account's public profile for this viewer (core#744), for modules that
     * show one in their own answer (`GET /miembros/{handle}/tarjeta` answers the same card as JSON).
     * Null when the viewer may not see the profile, the account has no profile (unknown, not a valid
     * id, deactivated, anonymized, no profile row) or anything fails; it never throws.
     */
    public function card(string $userId, ?Authenticatable $viewer): ?ProfileCardDTO;
}
