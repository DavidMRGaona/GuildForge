<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\Events;

/**
 * Somebody gave the account's password and then wrong codes, TwoFactorChallengeRule::ALERT_AFTER_FAILURES
 * times within an hour (core#770): the password is probably known to them. At most once an hour per account.
 */
final readonly class RepeatedTwoFactorCodeFailures
{
    public function __construct(
        public string $userId,
    ) {}
}
