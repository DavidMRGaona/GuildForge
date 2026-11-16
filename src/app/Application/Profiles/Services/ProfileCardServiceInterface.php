<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileCardDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The summary card of a public profile for modules (core#744), through the same ProfileAccessRule as the
 * page: what `GET /miembros/{handle}/tarjeta` answers as JSON, for a module that shows one in its own
 * answer. An interface of its own, apart from PublicProfileServiceInterface, so a module's test doubles
 * of it keep working when the other grows.
 */
interface ProfileCardServiceInterface
{
    /**
     * The card of this account's public profile for this viewer (the person who will see it, null for a
     * guest). Null when the viewer may not see the profile, the account has no profile (not a UUID,
     * unknown, deactivated, anonymized, no profile row) or anything fails; it never throws. The id is
     * read without regard to case.
     */
    public function card(string $userId, ?Authenticatable $viewer): ?ProfileCardDTO;
}
