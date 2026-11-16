<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

use App\Application\TwoFactor\DTOs\TwoFactorSetupDTO;
use App\Application\TwoFactor\DTOs\TwoFactorStatusDTO;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;

/**
 * Two-factor authentication of an account (core#197): a TOTP secret (RFC 6238, six digits,
 * 30-second steps, one step of leeway on each side) and eight single-use recovery codes. The only
 * place that stores secrets or checks codes: the site, the panel, roles and administration all go
 * through it.
 *
 * - A code is never accepted twice: each check runs under a row lock and only takes a step later
 *   than the last one accepted for the account.
 * - Secrets are encrypted at rest and recovery codes are stored as hashes; neither is ever logged.
 * - Turning it on closes the account's other sessions and invalidates its «remember me» cookies,
 *   after the commit, so every session of an account with two-factor authentication passed it.
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
     * only time they are ever shown. Closes every other session of the account (the one given
     * stays) and rotates its remember token, after the commit.
     *
     * @param  string|null  $currentSessionId  the session to keep open (the request's); null closes them all
     * @return list<string>
     *
     * @throws TwoFactorException NoPendingSetup, AlreadyEnabled, InvalidCode, Impersonating
     */
    public function confirmSetup(string $userId, #[\SensitiveParameter] string $code, ?string $currentSessionId = null): array;

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
