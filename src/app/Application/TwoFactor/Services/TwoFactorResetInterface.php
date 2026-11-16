<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

use App\Domain\TwoFactor\Exceptions\TwoFactorException;

/**
 * Taking away an account's two-factor authentication on behalf of the administration (core#771): the
 * way back in for someone who lost their phone and their recovery codes. Kept apart from
 * TwoFactorServiceInterface so test doubles of that one keep working.
 *
 * Who may do it is decided by the caller (the panel's policy, or the console, which has no user):
 * this only does it.
 */
interface TwoFactorResetInterface
{
    /**
     * Deletes the account's secret and recovery codes, signs out every session of the account and
     * invalidates its «remember me» cookies (a new session seal and remember token, as when the
     * person turns it off themselves, but with no session kept), and announces it after the commit so
     * the person is mailed. A setup that was never confirmed is dropped without announcing anything.
     *
     * @param  string|null  $actorId  who asked, for the event; null from the console
     * @return bool true when two-factor authentication was on and is now off; false when there was
     *              nothing to take away
     *
     * @throws TwoFactorException when the account does not exist, or while an administrator is
     *                            impersonating (nothing is changed or mailed then)
     */
    public function reset(string $userId, ?string $actorId): bool;
}
