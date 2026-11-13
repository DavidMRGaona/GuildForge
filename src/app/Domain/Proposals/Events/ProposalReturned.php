<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Events;

use App\Domain\Proposals\Enums\ProposalType;

/**
 * A reviewer sent a proposal back to a draft with a reason. Dispatched after the commit.
 */
final readonly class ProposalReturned
{
    public function __construct(
        public ProposalType $type,
        public string $id,
        public ?string $proposerId,
        public ?string $reviewerId,
        public string $reason,
    ) {}
}
