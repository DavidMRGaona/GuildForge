<?php

declare(strict_types=1);

namespace App\Application\Impersonation\Services;

use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Impersonation\Exceptions\ImpersonationNotAllowedException;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Starts and ends an impersonation in the administrator's own session. Nobody signs in:
 * starting stores the state in the session and the next requests run as the target (see
 * ImpersonationContextInterface). The session id changes when it starts and when it ends.
 */
interface ImpersonationServiceInterface
{
    /**
     * Checks ImpersonationEligibilityRule, opens the log entry and stores the state. The
     * identity changes from the next request on.
     *
     * @throws ImpersonationNotAllowedException
     */
    public function start(Authenticatable $actor, Authenticatable $target, ImpersonationMode $mode): void;

    /**
     * Starts a view as a set of roles instead of an account: the base role is always added,
     * ImpersonationEligibilityRule checks the actor and the roles, and the state holds the
     * role ids with a target id that belongs to no account.
     *
     * @param  list<string>  $roleIds
     *
     * @throws ImpersonationNotAllowedException
     */
    public function startWithRoles(Authenticatable $actor, array $roleIds, ImpersonationMode $mode): void;

    /**
     * Closes the log entry with the reason, drops the state and, in a request that runs as
     * the target, gives the guard back to the administrator. Does nothing without an
     * impersonation. The state is dropped even when the entry cannot be closed (it throws).
     */
    public function stop(ImpersonationEndReason $reason): void;
}
