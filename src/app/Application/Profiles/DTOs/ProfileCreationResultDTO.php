<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

/**
 * What `profiles:create-missing` did (or would do, in a dry run): profiles created, addresses moved back
 * to follow the public name, and accounts whose row could not be created or moved this time (each one
 * logged).
 */
final readonly class ProfileCreationResultDTO
{
    public function __construct(
        public int $created,
        public int $realigned = 0,
        public int $failed = 0,
    ) {}
}
