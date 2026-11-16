<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

use App\Application\TwoFactor\DTOs\TwoFactorSetupDTO;
use App\Application\TwoFactor\DTOs\TwoFactorStatusDTO;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use Illuminate\Contracts\Session\Session;

/**
 * Two-factor authentication of an account (core#197): a TOTP secret (RFC 6238, six digits,
 * 30-second steps, one step of leeway on each side) and eight single-use recovery codes. The only
 * place that stores secrets or checks codes: the site, the panel, roles and administration all go
 * through it.
 *
 * - A code is never accepted twice: each check runs under a row lock and only takes a step later
 *   than the last one accepted for the account.
 * - Secrets are encrypted at rest and recovery codes are stored as hashes; neither is ever logged.
 * - Turning it on gives the account a new session seal (TwoFactorSessionSealInterface), closes
 *   its other sessions and invalidates its «remember me» cookies, after the commit, so every
 *   session of an account with two-factor authentication passed it.
 * - Every change sends a mail in the mandatory category core.account-security (through the
 *   events in App\Domain\TwoFactor\Events, dispatched after the commit).
 * - Changes are refused while an administrator impersonates the account.
 *
 * Methods that change something throw TwoFactorException with a TwoFactorFailure reason.
 * Resetting it from the panel (core#771) lives in an interface of its own, so test doubles of this
 * one keep working.
 */
interface TwoFactorServiceInterface extends TwoFactorStatusReaderInterface
{
    public function status(string $userId): TwoFactorStatusDTO;

    /**
     * Starts setting it up: a new secret, replacing a setup not confirmed yet, never one in use.
     *
     * @throws TwoFactorException AlreadyEnabled, UnknownAccount, Impersonating
     */
    public function beginSetup(string $userId): TwoFactorSetupDTO;

    /**
     * Turns it on with a code from the app, and returns the recovery codes in plain text: the
     * only time they are ever shown. After the commit: the session given is stamped with the new
     * seal and stays signed in, every other one is closed (rows deleted with the database driver,
     * signed out on their next request with any driver) and the remember token rotates.
     *
     * @param  Session|null  $currentSession  the session to keep signed in (the request's); null keeps none
     * @return list<string>
     *
     * @throws TwoFactorException NoPendingSetup, AlreadyEnabled, InvalidCode, Impersonating
     */
    public function confirmSetup(string $userId, #[\SensitiveParameter] string $code, ?Session $currentSession = null): array;

    /**
     * Checks a code from the app for an account with it on. False for a wrong, malformed, expired
     * or already used code, and when it is off. Accepting a code uses up its step.
     */
    public function verify(string $userId, #[\SensitiveParameter] string $code): bool;

    /**
     * Uses up a recovery code. False for a wrong, malformed or already used one, and when it is off.
     */
    public function useRecoveryCode(string $userId, #[\SensitiveParameter] string $code): bool;

    /**
     * New recovery codes in place of every old one, returned in plain text only this time.
     *
     * @return list<string>
     *
     * @throws TwoFactorException NotEnabled, Impersonating
     */
    public function regenerateRecoveryCodes(string $userId): array;

    /**
     * Turns it off: secret and recovery codes are deleted.
     *
     * @throws TwoFactorException NotEnabled, Impersonating
     */
    public function disable(string $userId): void;
}
