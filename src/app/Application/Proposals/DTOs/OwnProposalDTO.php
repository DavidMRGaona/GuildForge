<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use DateTimeImmutable;

/**
 * A proposal as its own author sees it (core#579): the review state and what the form holds.
 */
final readonly class OwnProposalDTO
{
    /**
     * @param  ?string  $body  the event's description or the article's content (HTML)
     * @param  list<string>  $tagIds
     * @param  ?string  $reason  why the last review returned or rejected it
     * @param  list<StoredProposalRequestDTO>  $requests  what an event's author asked the modules for
     */
    public function __construct(
        public ProposalType $type,
        public string $id,
        public ProposalStatus $status,
        public string $title,
        public ?string $body,
        public ?string $excerpt,
        public ?string $location,
        public ?DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
        public ?string $imagePublicId,
        public array $tagIds,
        public ?string $note,
        public ?string $reason,
        public ?DateTimeImmutable $submittedAt,
        public ?DateTimeImmutable $reviewedAt,
        public ?DateTimeImmutable $updatedAt,
        public ?string $slug = null,
        public array $requests = [],
    ) {}
}
