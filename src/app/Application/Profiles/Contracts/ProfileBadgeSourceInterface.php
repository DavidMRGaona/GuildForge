<?php

declare(strict_types=1);

namespace App\Application\Profiles\Contracts;

use App\Application\Profiles\DTOs\ProfileBadgeDTO;

/**
 * Where the featured badges of public profiles come from (core#776): the achievements module gives
 * the ones each owner chose. A module returns its classes from registerProfileBadgeSources(); each one
 * is built from the container again in every request or job that draws badges.
 *
 * The core asks every source once per page, with every author whose profile the person viewing may
 * see (ProfileAccessRule decides before; a source never hears about the rest), so a source answers
 * for all of them with a single query, never one per account. The badges go on the profile under
 * the name, on the profile card and next to each author in the modules that show authors (the
 * forum), through ProfileBadgesInterface.
 *
 * A source that throws, or answers in another shape, gives nothing for that page; the other sources
 * still give theirs. Each badge is checked as ProfileBadgeDTO says, and only the exception class of
 * a failure is logged, once a day.
 */
interface ProfileBadgeSourceInterface
{
    /**
     * The featured badges of each account: at most ProfileBadgesInterface::MAX_PER_ACCOUNT, in the
     * owner's order. An account with none may be left out; accounts not asked about are ignored.
     *
     * @param  list<string>  $userIds  account ids, in lower case, each once
     * @return array<string, list<ProfileBadgeDTO>> account id => badges
     */
    public function badgesFor(array $userIds): array;
}
