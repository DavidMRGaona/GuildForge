<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

use App\Application\TwoFactor\DTOs\TwoFactorSetupDTO;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use Illuminate\Contracts\Session\Session;

/**
 * Changing the authenticator app of an account that has two-factor authentication on (core#769).
 * In an interface of its own, so test doubles of TwoFactorServiceInterface keep working.
 *
 * The secret in use keeps working until a code from the new app is confirmed, and the recovery
 * codes stay. Confirming renews the session seal like turning it on or off (TwoFactorSessionSealInterface):
 * the session given stays signed in, every other one is signed out and older «remember me»
 * cookies stop working. The caller proves the factor in use before starting (this does not check
 * it). Like the rest, it is refused while an administrator impersonates the account and sends a
 * security mail.
 */
interface TwoFactorAppChangeInterface
{
    /**
     * A new secret, kept apart in place of one not confirmed yet. Never touches the one in use.
     *
     * @throws TwoFactorException NotEnabled, Impersonating, Unavailable
     */
    public function beginAppChange(string $userId): TwoFactorSetupDTO;

    /**
     * Swaps the secret for the new one with a code from the new app. A code of the app in use is
     * not accepted, and the one used to confirm cannot be used again to sign in.
     *
     * @param  Session|null  $currentSession  the session to keep signed in (the request's); null keeps none
     *
     * @throws TwoFactorException NotEnabled, NoPendingSetup, InvalidCode, Impersonating, Unavailable
     */
    public function confirmAppChange(string $userId, #[\SensitiveParameter] string $code, ?Session $currentSession = null): void;
}
