<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

use DateTimeImmutable;

final readonly class GalleryResponseDTO
{
    /**
     * @param  array<TagResponseDTO>  $tags
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public ?string $description,
        public bool $isPublished,
        public bool $isFeatured,
        public int $photoCount,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $tags = [],
    ) {}
}
