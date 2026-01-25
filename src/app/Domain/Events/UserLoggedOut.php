<?php

declare(strict_types=1);

namespace App\Domain\Events;

final readonly class UserLoggedOut
{
    public function __construct(
        public string $userId,
    ) {
    }
}
