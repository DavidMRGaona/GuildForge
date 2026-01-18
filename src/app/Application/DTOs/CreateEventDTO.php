<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use DateMalformedStringException;
use DateTimeImmutable;

final readonly class CreateEventDTO
{
    public function __construct(
        public string $title,
        public string $description,
        public DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate = null,
        public ?string $location = null,
        public ?string $imagePublicId = null,
        public ?float $memberPrice = null,
        public ?float $nonMemberPrice = null,
    ) {
    }

    /**
     * @param array{title: string, description: string, start_date: string, end_date?: string|null, location?: string|null, image_public_id?: string|null, member_price?: float|null, non_member_price?: float|null} $data
     *
     * @throws DateMalformedStringException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'],
            startDate: new DateTimeImmutable($data['start_date']),
            endDate: isset($data['end_date']) ? new DateTimeImmutable($data['end_date']) : null,
            location: $data['location'] ?? null,
            imagePublicId: $data['image_public_id'] ?? null,
            memberPrice: $data['member_price'] ?? null,
            nonMemberPrice: $data['non_member_price'] ?? null,
        );
    }
}
