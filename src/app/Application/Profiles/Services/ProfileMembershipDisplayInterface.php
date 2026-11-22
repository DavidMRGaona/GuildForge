<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Domain\Profiles\Exceptions\ProfileMembershipDisplayUnavailableException;
use InvalidArgumentException;

/**
 * Whether an account shows that it is an association member to the other members (core#824): what a
 * module that must not reveal membership (the achievements' years as a member) asks before it reports
 * anything about it.
 *
 * True only when everything the «Socio» badge of the profile page asks holds: the account has a profile
 * row, is neither deactivated nor anonymized, has a public name, turned the badge on (`show_membership`),
 * its profile is visible to any signed-in member (`public` or `members`), and it is an association member
 * now (the rule of AssociationMembersAmongInterface, the batched form of AssociationMembershipServiceInterface).
 *
 * It answers the same for everybody: there is no viewer, and it never reads the signed-in user, the
 * session or the request, so a queued job can call it. It reads the profile rows with their accounts and
 * the roles of those that pass at once, a constant number of queries per call.
 *
 * A failure is not an answer. Whatever cannot be read (the role setting, the database, the two-factor
 * reader) throws ProfileMembershipDisplayUnavailableException, never `false`: `false` is «does not show it»,
 * and a consumer that took a failure for it would delete real facts. Asking about more than MAX_IDS
 * accounts throws InvalidArgumentException; nothing is cut in silence. A source lets the exception go up,
 * and the activity reader then marks the answer as failed.
 *
 * It is only as fresh as the database: when the answer may change, ProfileMembershipDisplayChanged says so.
 */
interface ProfileMembershipDisplayInterface
{
    /** Accounts asked about in one call at most (a history page has 500 at most) */
    public const int MAX_IDS = 500;

    /**
     * @param  list<string>  $userIds  read in lower case, repeated ids and anything that is not an account id ignored
     * @return array<string, bool> account id (lower case) => whether it shows its membership, for every valid id asked
     *
     * @throws ProfileMembershipDisplayUnavailableException when something cannot be read
     * @throws InvalidArgumentException when more than MAX_IDS accounts are asked about
     */
    public function showsMembership(array $userIds): array;
}
