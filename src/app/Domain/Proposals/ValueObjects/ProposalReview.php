<?php

declare(strict_types=1);

namespace App\Domain\Proposals\ValueObjects;

use App\Domain\Proposals\Enums\ProposalAuthorAction;
use App\Domain\Proposals\Enums\ProposalStatus;
use DateTimeImmutable;

/**
 * The review data of an event or article proposed from the web. Content created in the panel
 * carries none.
 */
final readonly class ProposalReview
{
    public function __construct(
        public ProposalStatus $status,
        public ?string $proposedBy,
        public ?string $note = null,
        public ?string $reason = null,
        public ?DateTimeImmutable $submittedAt = null,
        public ?DateTimeImmutable $reviewedAt = null,
        public ?string $reviewedBy = null,
    ) {}

    public function isUnapproved(): bool
    {
        return ProposalStatus::isUnapproved($this->status);
    }

    public function allowsAuthor(ProposalAuthorAction $action): bool
    {
        return ProposalStatus::allowsAuthor($this->status, $action);
    }

    /** Never sent to review: only its author sees it */
    public function isUnsubmittedDraft(): bool
    {
        return $this->status === ProposalStatus::Draft && ! $this->submittedAt instanceof DateTimeImmutable;
    }

    /** Sent back to a draft by a reviewer (it had been submitted) */
    public function isReturned(): bool
    {
        return $this->status === ProposalStatus::Draft && $this->submittedAt instanceof DateTimeImmutable;
    }
}
