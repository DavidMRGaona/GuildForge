<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\DTOs;

use App\Domain\TwoFactor\TwoFactorFailure;

/**
 * What checking a code at sign-in said. A refused code carries why: InvalidCode (wrong, already
 * used or malformed), TooManyAttempts (not checked: the account's limit, with the seconds to wait)
 * or Unavailable (it could not be checked now). Only InvalidCode counts as a wrong code.
 */
final readonly class TwoFactorChallengeCheckDTO
{
    private function __construct(
        public bool $passed,
        public ?TwoFactorFailure $failure,
        /** Only after a recovery code was used: how many are left */
        public ?int $recoveryCodesRemaining,
        /** Only with TooManyAttempts: seconds until another code is checked */
        public ?int $retryAfter,
    ) {}

    public static function passed(?int $recoveryCodesRemaining = null): self
    {
        return new self(true, null, $recoveryCodesRemaining, null);
    }

    public static function refused(TwoFactorFailure $failure): self
    {
        return new self(false, $failure, null, null);
    }

    public static function tooManyAttempts(int $retryAfter): self
    {
        return new self(false, TwoFactorFailure::TooManyAttempts, null, max(1, $retryAfter));
    }

    public function countsAsFailure(): bool
    {
        return $this->failure === TwoFactorFailure::InvalidCode;
    }
}
