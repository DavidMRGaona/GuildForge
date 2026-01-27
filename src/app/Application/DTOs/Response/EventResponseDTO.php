<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

use DateTimeImmutable;

final readonly class EventResponseDTO
{
    /**
     * @param  array<TagResponseDTO>  $tags
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public ?string $description,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public ?string $location,
        public ?float $memberPrice,
        public ?float $nonMemberPrice,
        public ?string $imagePublicId,
        public bool $isPublished,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $tags = [],
    ) {
    }
}
