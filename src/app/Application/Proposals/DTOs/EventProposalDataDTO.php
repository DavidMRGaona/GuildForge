<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

use DateTimeImmutable;

/**
 * What a person may write in an event proposal: no prices, download links nor publication. The
 * dates are instants (the web form reads them as display-zone wall times).
 */
final readonly class EventProposalDataDTO
{
    /**
     * @param  list<string>  $tagIds  tags that do not exist or do not apply to events are ignored
     * @param  ?string  $note  private note for the administration
     * @param  ?array<array-key, mixed>  $moduleRequests  what the form sent for the module requests
     *                                                    (EventProposalRequestServiceInterface); null leaves the stored ones alone
     * @param  ?string  $categoryId  only taken from whoever may publish events directly
     *                               (ProposalTagOptionsInterface); null keeps the stored category
     */
    public function __construct(
        public string $title,
        public string $description,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public ?string $location = null,
        public ?string $imagePublicId = null,
        public array $tagIds = [],
        public ?string $note = null,
        public ?array $moduleRequests = null,
        public ?string $categoryId = null,
    ) {}
}
