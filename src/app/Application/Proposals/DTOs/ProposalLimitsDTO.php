<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

/**
 * How much of the caps of the proposals settings a person has used (core#579), across both types.
 */
final readonly class ProposalLimitsDTO
{
    public function __construct(
        public int $pendingUsed,
        public int $pendingMax,
        public int $draftsUsed,
        public int $draftsMax,
        public int $submissionsLeftThisHour,
        public int $submissionsPerHour,
    ) {}
}
