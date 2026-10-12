<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

use DateTimeImmutable;

final readonly class GalleryDetailResponseDTO
{
    /**
     * @param  array<int, PhotoResponseDTO>  $photos
     * @param  array<TagResponseDTO>  $tags
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public ?string $description,
        public bool $isPublished,
        public bool $isFeatured,
        public array $photos,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $tags = [],
    ) {}
}
