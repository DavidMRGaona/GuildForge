<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Events;

use App\Domain\Proposals\Enums\ProposalType;

/**
 * The author took a proposal back from review to a draft. Dispatched after the commit.
 */
final readonly class ProposalWithdrawn
{
    public function __construct(
        public ProposalType $type,
        public string $id,
        public string $proposerId,
    ) {}
}
