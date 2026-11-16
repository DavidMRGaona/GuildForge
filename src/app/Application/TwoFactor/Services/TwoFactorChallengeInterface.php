<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

use App\Application\TwoFactor\DTOs\TwoFactorChallengeCheckDTO;
use Illuminate\Contracts\Session\Session;

/**
 * The second step of signing in for an account with two-factor authentication on (core#770), shared
 * by the site and the panel. Neither of them signs such an account in, nor queues a «remember me»
 * cookie, before check() accepted a code.
 *
 * After the code, always in this order: login($user, $remember), regenerate the session, stamp
 * the seal (TwoFactorSessionSealInterface::stamp()) and, with «remember me»,
 * rememberAfterChallenge(). Without the seal the new session is signed out on its next request.
 */
interface TwoFactorChallengeInterface
{
    /**
     * Checks a code from the app or, with $recoveryCode, uses up a recovery code. An accepted code
     * is used up: a second call with it is refused, so a screen that may validate twice keeps the
     * first answer.
     *
     * Every call counts against the account's limits (TwoFactorChallengeRule), shared by the site
     * and the panel; past them it answers TooManyAttempts without checking anything. A refusal is
     * logged with the account and the reason only, never the code. The tenth wrong code within an
     * hour mails the owner (RepeatedTwoFactorCodeFailures).
     */
    public function check(string $userId, #[\SensitiveParameter] string $code, bool $recoveryCode): TwoFactorChallengeCheckDTO;

    /**
     * Signs the account in on the site's guard once check() accepted the code, in the order
     * above, and dispatches UserLoggedIn. False, signing nobody in, when the account is gone.
     */
    public function signIn(Session $session, string $userId, bool $remember): bool;

    /**
     * The rest of the order above for a sign-in already made by someone else (the panel's login
     * page, which calls login() itself): regenerate, stamp and, with «remember me», the cookie.
     */
    public function completeSignIn(Session $session, string $userId, bool $remember): void;
}
