<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\DTOs;

/**
 * A sign-in of the site waiting for its second factor (core#770): the password was right, nobody
 * is signed in yet. «Remember me» is kept here and only applied once the code is accepted.
 */
final readonly class PendingTwoFactorLoginDTO
{
    public function __construct(
        /** Random, one per pending sign-in: what the attempts are counted by */
        public string $token,
        public string $userId,
        public bool $remember,
        public int $startedAt,
        public int $failures,
    ) {}
}
