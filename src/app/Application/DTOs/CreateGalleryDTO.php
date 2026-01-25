<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class CreateGalleryDTO
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $coverImagePublicId = null,
    ) {
    }

    /**
     * @param  array{title: string, description?: string|null, cover_image_public_id?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'] ?? null,
            coverImagePublicId: $data['cover_image_public_id'] ?? null,
        );
    }
}
