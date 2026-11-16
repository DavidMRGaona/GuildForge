<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;

/**
 * The account's session seal (core#197: every session of an account with two-factor
 * authentication has passed it). A random value of the account, copied into each session when it
 * signs in; only a session holding the current one stays signed in, whatever the session driver.
 *
 * - renew() gives the account a new seal, so every session opened before is signed out on its
 *   next request: turning two-factor authentication on or off does it, and so does resetting it
 *   from the panel (core#771).
 * - Each sign-in copies the seal into the session (a Login listener), except for an account with
 *   two-factor authentication on that did not come from a «remember me» cookie: the sign-in
 *   challenge (site and panel, core#770) calls stamp() right after the code is accepted.
 * - An account that never had a seal keeps every session, so deploying it signs nobody out.
 * - While impersonating, the administrator's seal is the one checked, never the target's.
 */
interface TwoFactorSessionSealInterface
{
    /**
     * Copies the account's current seal into the session (and drops any other one it held).
     */
    public function stamp(Session $session, string $userId): void;

    /**
     * Whether the session may stay signed in as its owner: always when the account never had a
     * seal, otherwise only holding the current one. Fails closed: a seal it cannot read keeps no
     * session.
     */
    public function passes(Session $session, Authenticatable $owner): bool;

    /**
     * A new seal for the account, after the commit when called inside a transaction: it is stamped
     * into $keep (which stays signed in), every other session is signed out on its next request
     * (and its row deleted with the database driver) and the remember token rotates, so no older
     * «remember me» cookie signs in again.
     */
    public function renew(string $userId, ?Session $keep = null): void;
}
