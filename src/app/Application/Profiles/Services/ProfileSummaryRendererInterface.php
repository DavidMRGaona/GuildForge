<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ResolvedProfileDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The summary of a public profile, for its card (core#744): the header and the first
 * ResolvedProfileDTO::CARD_STATS stats that can be drawn, in the owner's order. No section is built
 * (`main` and `aside` are empty) and, once the card is full, no further stat is counted.
 *
 * Like ProfileRendererInterface, which the same instance implements, it never decides whether the
 * person may see the profile and fails closed without throwing. Only the core calls it, through
 * PublicProfileServiceInterface::card(), once the access rule let the person see the profile;
 * modules ask for the card instead.
 *
 * An interface of its own rather than a method of ProfileRendererInterface, so test doubles of the
 * renderer keep working.
 */
interface ProfileSummaryRendererInterface
{
    public function summary(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ResolvedProfileDTO;
}
