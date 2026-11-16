<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

use Illuminate\Contracts\Session\Session;

/**
 * Proof in a session that it passed the second factor (core#197: every session of an account with
 * two-factor authentication has passed it). Turning it on gives the account a new random seal;
 * only a session holding it stays signed in, whatever the session driver, so sessions opened
 * before (and any not stamped since) are closed on their next request.
 *
 * Whoever lets a session through the second factor stamps it: the sign-in challenge (site and
 * panel, core#770) right after the code is accepted, and TwoFactorServiceInterface::confirmSetup()
 * for the session that turned it on. A session restored from a «remember me» cookie is stamped
 * by the check itself: those cookies are only issued after the challenge, and turning it on
 * invalidates every older one.
 */
interface TwoFactorSessionSealInterface
{
    /**
     * Copies the account's current seal into the session (and drops any other one it held).
     * Nothing to copy without two-factor authentication on.
     */
    public function stamp(Session $session, string $userId): void;

    /**
     * Whether the session may stay signed in as the account: always without two-factor
     * authentication on, otherwise only holding the current seal. Fails closed: an account it
     * cannot read keeps no session.
     */
    public function passes(Session $session, string $userId): bool;
}
