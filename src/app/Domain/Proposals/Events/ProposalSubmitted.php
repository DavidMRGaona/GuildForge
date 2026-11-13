<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Events;

use App\Domain\Proposals\Enums\ProposalType;

/**
 * A proposal was sent to review and awaits it (not dispatched when submitting published it
 * directly: that is ProposalApproved). Dispatched after the commit.
 */
final readonly class ProposalSubmitted
{
    public function __construct(
        public ProposalType $type,
        public string $id,
        public string $proposerId,
    ) {}
}
