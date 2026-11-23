<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\VisibleProfileDTO;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * Which of many accounts' public profiles one person may see (core#840): what a module that lists
 * people (a leaderboard, a list of participants) asks before it names or links anybody. One call for
 * the whole list: the profile rows and their accounts are read once, and ProfileAccessRule, the rule
 * of the profile page, asks about the viewer at most once for all of them.
 *
 * Only the profiles the viewer may see come back, in the order of the ids, each once, with only what
 * the page shows them (VisibleProfileDTO): never a hidden, deactivated or anonymized profile, an account
 * without a profile row, nor, for anybody but the owner, a profile without a public name. Never the
 * account name, the email or the roles.
 *
 * Asking about more than MAX_IDS accounts throws InvalidArgumentException before anything is read:
 * nothing is cut in silence, so a longer list is asked in chunks. Any other failure (the database, a row
 * the page would not answer) fails closed: it gives no profile and is logged with the exception's class.
 *
 * An interface of its own, apart from PublicProfileServiceInterface and ProfileCardServiceInterface, so
 * the test doubles modules keep of those keep working.
 */
interface VisibleProfilesInterface
{
    /** Accounts asked about in one call at most */
    public const int MAX_IDS = 500;

    /**
     * @param  list<string>  $userIds  read in lower case, repeated ids and anything that is not an account id ignored
     * @param  Authenticatable|null  $viewer  the person viewing (the request's user), null for a guest
     * @return list<VisibleProfileDTO> the profiles the viewer may see, in the order of the ids
     *
     * @throws InvalidArgumentException when more than MAX_IDS accounts are asked about
     */
    public function among(array $userIds, ?Authenticatable $viewer): array;
}
