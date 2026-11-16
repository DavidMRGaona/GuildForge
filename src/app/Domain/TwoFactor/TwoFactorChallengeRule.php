<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor;

/**
 * Limits of the sign-in challenge (core#770), the same on the site and in the panel:
 *
 * - A pending sign-in lasts five minutes and is dropped after ten wrong codes, so the password has
 *   to be given again.
 * - Codes checked per account, on the site and in the panel together: five a minute and thirty an
 *   hour, whatever the pending sign-in or the address.
 * - The tenth wrong code of an account within an hour mails its owner, once in that hour.
 */
final class TwoFactorChallengeRule
{
    public const int PENDING_SECONDS = 300;

    public const int ATTEMPTS_PER_MINUTE = 5;

    public const int ATTEMPTS_PER_HOUR = 30;

    public const int MAX_FAILURES = 10;

    public const int ALERT_AFTER_FAILURES = 10;

    public static function hasExpired(int $startedAt, int $now): bool
    {
        return $now - $startedAt > self::PENDING_SECONDS;
    }

    public static function isExhausted(int $failures): bool
    {
        return $failures >= self::MAX_FAILURES;
    }
}
