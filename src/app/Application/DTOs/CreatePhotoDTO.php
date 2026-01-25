<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class CreatePhotoDTO
{
    public function __construct(
        public string $galleryId,
        public string $imagePublicId,
        public ?string $caption = null,
        public int $sortOrder = 0,
    ) {
    }

    /**
     * @param  array{gallery_id: string, image_public_id: string, caption?: string|null, sort_order?: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            galleryId: $data['gallery_id'],
            imagePublicId: $data['image_public_id'],
            caption: $data['caption'] ?? null,
            sortOrder: $data['sort_order'] ?? 0,
        );
    }
}
