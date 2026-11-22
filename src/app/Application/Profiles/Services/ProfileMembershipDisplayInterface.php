<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

/**
 * Whether an account shows that it is an association member to the other members (core#824): what a
 * module that must not reveal membership (the achievements' years as a member) asks before it reports
 * anything about it.
 *
 * True only when everything the «Socio» badge of the profile page asks holds: the account has a profile
 * row, is neither deactivated nor anonymized, has a public name, turned the badge on (`show_membership`),
 * its profile is visible to any signed-in member (`public` or `members`), and it is an association member
 * now (AssociationMembershipServiceInterface's role).
 *
 * It answers the same for everybody: there is no viewer, and it never reads the signed-in user, the
 * session or the request, so a queued job can call it. It never throws and fails closed: whatever cannot be
 * read is false, warned about once a day. It reads the profile rows, the accounts and the association role's
 * holders at once, a constant number of queries per call.
 *
 * It is only as fresh as the database: when the answer may change, ProfileMembershipDisplayChanged says so.
 */
interface ProfileMembershipDisplayInterface
{
    /** Accounts asked about in one call; the rest are false (a history page has 500 at most) */
    public const int MAX_IDS = 500;

    /**
     * @param  list<string>  $userIds  read in lower case, repeated ids and anything that is not an account id ignored
     * @return array<string, bool> account id (lower case) => whether it shows its membership, for every valid id asked
     */
    public function showsMembership(array $userIds): array;
}
