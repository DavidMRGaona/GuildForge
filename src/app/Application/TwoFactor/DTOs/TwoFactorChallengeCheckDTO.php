<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\DTOs;

use App\Domain\TwoFactor\TwoFactorFailure;

/**
 * What checking a code at sign-in said. A refused code carries why: InvalidCode (wrong, already
 * used or malformed) or Unavailable (it could not be checked now, so it does not count as a failure).
 */
final readonly class TwoFactorChallengeCheckDTO
{
    private function __construct(
        public bool $passed,
        public ?TwoFactorFailure $failure,
        /** Only after a recovery code was used: how many are left */
        public ?int $recoveryCodesRemaining,
    ) {}

    public static function passed(?int $recoveryCodesRemaining = null): self
    {
        return new self(true, null, $recoveryCodesRemaining);
    }

    public static function refused(TwoFactorFailure $failure): self
    {
        return new self(false, $failure, null);
    }

    public function countsAsFailure(): bool
    {
        return ! $this->passed && $this->failure !== TwoFactorFailure::Unavailable;
    }
}
