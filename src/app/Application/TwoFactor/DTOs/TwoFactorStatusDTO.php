<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\DTOs;

use DateTimeImmutable;

/**
 * Where an account stands with two-factor authentication.
 */
final readonly class TwoFactorStatusDTO
{
    public function __construct(
        /** On: the setup was confirmed with a code, so every sign-in asks for one */
        public bool $enabled,
        /** When it was turned on; null while off */
        public ?DateTimeImmutable $confirmedAt,
        /** Unused recovery codes left; 0 while off */
        public int $recoveryCodesRemaining,
        /** A setup was started and not confirmed yet (only possible while off) */
        public bool $setupPending,
    ) {}

    public static function off(bool $setupPending = false): self
    {
        return new self(false, null, 0, $setupPending);
    }
}
