<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\Events;

/**
 * The person confirmed a new authenticator app (core#769). Dispatched after the commit.
 */
final readonly class TwoFactorAppChanged
{
    public function __construct(
        public string $userId,
    ) {}
}
