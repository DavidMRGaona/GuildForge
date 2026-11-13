<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Steps;

use App\Application\Proposals\Contracts\ProposalApprovalStepInterface;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Domain\Proposals\Enums\ProposalType;

/**
 * On approving an event proposal, applies every module request ticked to be applied (core#581):
 * with the reviewer's values, or the requested ones when it is published directly. The first one
 * that cannot be applied stops the whole approval.
 */
final readonly class ApplyEventProposalRequests implements ProposalApprovalStepInterface
{
    public function __construct(
        private EventProposalRequestServiceInterface $requests,
    ) {}

    public function apply(ProposalType $type, string $id, ?string $reviewerId): void
    {
        if ($type !== ProposalType::Event) {
            return;
        }

        $this->requests->applyTicked($id, directly: $reviewerId === null);
    }
}
