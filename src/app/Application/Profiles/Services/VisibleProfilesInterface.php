<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\VisibleProfileDTO;
use App\Domain\Profiles\Exceptions\VisibleProfilesUnavailableException;
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
 * the page would not answer) is logged with the exception's class only, and then:
 *
 * - among() fails closed: it gives no profile, so a failure and «nobody is visible» look alike;
 * - amongOrFail() (core#871) throws VisibleProfilesUnavailableException, with no cause and no detail,
 *   so whoever must tell them apart (a leaderboard saying «not available» rather than «nobody») can.
 *
 * Both read and decide alike, through ProfileAccessRule::visibleAmong(): a check the rule itself cannot
 * make (whether the viewer is an association member) hides the profiles that need it, as the profile page
 * does, and is not a failed read.
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

    /**
     * The same answer as among(), except that a failed read throws instead of giving no profile: an
     * empty list then always means that nobody among them is visible to the viewer.
     *
     * @param  list<string>  $userIds  read in lower case, repeated ids and anything that is not an account id ignored
     * @param  Authenticatable|null  $viewer  the person viewing (the request's user), null for a guest
     * @return list<VisibleProfileDTO> the profiles the viewer may see, in the order of the ids
     *
     * @throws InvalidArgumentException when more than MAX_IDS accounts are asked about
     * @throws VisibleProfilesUnavailableException when the profiles could not be read
     */
    public function amongOrFail(array $userIds, ?Authenticatable $viewer): array;
}
