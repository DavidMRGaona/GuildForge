<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileBadgeDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The featured badges of the authors of a page, for the person viewing it (core#776): what a module
 * that shows authors (the forum, next to each post) draws with `ProfileBadges.vue`. One call per page
 * with every author: the profile rows, the access rule and every source are asked once for all of
 * them, never once per author.
 *
 * Only the badges of profiles the viewer may see (ProfileAccessRule, the rule of the profile page):
 * nothing for a hidden, deactivated or anonymized profile, an account without a profile row or one
 * the viewer may not see. It never throws: any failure gives no badges, a failing source gives none
 * of its own and the others still give theirs, each warned about once a day.
 */
interface ProfileBadgesInterface
{
    /** The badges an account shows at most, from all the sources together */
    public const int MAX_PER_ACCOUNT = 3;

    /**
     * @param  list<string>  $userIds  the page's authors; read in lower case, repeated ids and anything that is not an account id ignored
     * @param  Authenticatable|null  $viewer  the person viewing (the request's user), null for a guest
     * @return array<string, list<ProfileBadgeDTO>> account id (lower case) => badges, in the order of
     *                                              the ids; an account without badges is left out
     */
    public function forAuthors(array $userIds, ?Authenticatable $viewer): array;
}
