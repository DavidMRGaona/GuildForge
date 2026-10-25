<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\GalleryId;
use App\Domain\ValueObjects\PhotoId;
use DateTimeImmutable;

final readonly class Photo
{
    public function __construct(
        private PhotoId $id,
        private GalleryId $galleryId,
        private string $imagePublicId,
        private ?string $caption = null,
        private int $sortOrder = 0,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function id(): PhotoId
    {
        return $this->id;
    }

    public function galleryId(): GalleryId
    {
        return $this->galleryId;
    }

    public function imagePublicId(): string
    {
        return $this->imagePublicId;
    }

    public function caption(): ?string
    {
        return $this->caption;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Returns a new Photo instance with the updated sort order (immutable pattern).
     */
    public function withSortOrder(int $sortOrder): self
    {
        return new self(
            id: $this->id,
            galleryId: $this->galleryId,
            imagePublicId: $this->imagePublicId,
            caption: $this->caption,
            sortOrder: $sortOrder,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }
}
