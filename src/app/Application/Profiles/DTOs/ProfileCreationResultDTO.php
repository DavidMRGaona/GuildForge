<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

/**
 * What `profiles:create-missing` did: profiles created (or that would be, in a dry run) and accounts
 * whose profile could not be created this time (each one logged).
 */
final readonly class ProfileCreationResultDTO
{
    public function __construct(
        public int $created,
        public int $failed = 0,
    ) {}
}
