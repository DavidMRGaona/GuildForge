<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Events;

use App\Domain\Proposals\Enums\ProposalType;

/**
 * A proposal was approved and published, by a reviewer or, when $automatic, by submitting it
 * with the type's publish-directly permission. Dispatched after the commit.
 */
final readonly class ProposalApproved
{
    public function __construct(
        public ProposalType $type,
        public string $id,
        public ?string $proposerId,
        public ?string $reviewerId,
        public bool $automatic = false,
    ) {}
}
