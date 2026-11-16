<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\Events;

final readonly class RecoveryCodeUsed
{
    public function __construct(
        public string $userId,
        /** How many unused recovery codes the account has left */
        public int $remaining,
    ) {}
}
