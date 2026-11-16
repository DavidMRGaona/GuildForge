<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\Events;

final readonly class TwoFactorEnabled
{
    public function __construct(
        public string $userId,
    ) {}
}
