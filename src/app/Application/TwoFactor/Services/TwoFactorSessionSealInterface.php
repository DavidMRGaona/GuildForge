<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * The account's session seal (core#197: every session of an account with two-factor
 * authentication has passed it). A random value of the account, copied into each session when it
 * signs in; only a session holding the current one stays signed in, whatever the session driver.
 *
 * - renew() gives the account a new seal: turning two-factor authentication on or off does it, and
 *   so does resetting it from the panel (core#771). Sessions opened before are signed out on their
 *   next request and older «remember me» cookies stop working.
 * - Each sign-in copies the seal into the session (a Login listener), except for an account with
 *   two-factor authentication on: the sign-in challenge (site and panel, core#770) calls stamp()
 *   right after the code is accepted, and rememberAfterChallenge() when «remember me» was ticked.
 *   Restored from a «remember me» cookie, such an account only gets the seal when the request
 *   also carries the cookie rememberAfterChallenge() issued.
 * - An account that never had a seal keeps every session, so deploying it signs nobody out; one
 *   with two-factor authentication on and no seal keeps none (fails closed).
 * - While impersonating, the administrator's seal is the one checked, never the target's.
 */
interface TwoFactorSessionSealInterface
{
    /**
     * Copies the account's current seal into the session (and drops any other one it held).
     */
    public function stamp(Session $session, string $userId): void;

    /**
     * Whether the session may stay signed in as its owner. Fails closed: a seal it cannot read
     * keeps no session.
     */
    public function passes(Session $session, Authenticatable $owner): bool;

    /**
     * A new seal and remember token for the account, written at once (inside the caller's
     * transaction, so they commit or roll back with the change). After the commit, $keep gets the
     * new seal and stays signed in, and with the database driver the other session rows are
     * deleted (only a clean-up: the new seal already signs them out).
     */
    public function renew(string $userId, ?Session $keep = null): void;

    /**
     * Queues the cookie that lets a «remember me» cookie of an account with two-factor
     * authentication on sign in again: call it right after the challenge accepted the code, once
     * the guard has issued its own cookie. Bound to the account's remember token and seal, so
     * renew() invalidates it.
     */
    public function rememberAfterChallenge(string $userId): void;

    /**
     * Whether the request carries a valid cookie from rememberAfterChallenge() for the account.
     */
    public function rememberedAfterChallenge(Request $request, string $userId): bool;
}
