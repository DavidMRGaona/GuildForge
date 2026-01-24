<?php

declare(strict_types=1);

namespace App\Domain\Events;

final readonly class UserProfileUpdated
{
    public function __construct(
        public string $userId,
    ) {}
}
