<?php

declare(strict_types=1);

namespace App\Domain\Events;

final readonly class UserPasswordChanged
{
    public function __construct(
        public string $userId,
    ) {}
}
