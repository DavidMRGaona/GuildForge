<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor;

/**
 * Limits of the sign-in challenge (core#770), the same on the site and in the panel: a pending
 * sign-in lasts five minutes, takes five codes a minute, and is dropped after ten wrong ones, so
 * the password has to be given again.
 */
final class TwoFactorChallengeRule
{
    public const int PENDING_SECONDS = 300;

    public const int ATTEMPTS_PER_MINUTE = 5;

    public const int MAX_FAILURES = 10;

    public static function hasExpired(int $startedAt, int $now): bool
    {
        return $now - $startedAt > self::PENDING_SECONDS;
    }

    public static function isExhausted(int $failures): bool
    {
        return $failures >= self::MAX_FAILURES;
    }
}
