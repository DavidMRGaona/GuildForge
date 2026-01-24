<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\GalleryId;
use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;

final class Gallery
{
    public function __construct(
        private readonly GalleryId $id,
        private readonly string $title,
        private readonly Slug $slug,
        private readonly ?string $description = null,
        private readonly ?string $coverImagePublicId = null,
        private bool $isPublished = false,
        private readonly bool $isFeatured = false,
        private readonly ?DateTimeImmutable $createdAt = null,
        private readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function id(): GalleryId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function coverImagePublicId(): ?string
    {
        return $this->coverImagePublicId;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function publish(): void
    {
        $this->isPublished = true;
    }

    public function unpublish(): void
    {
        $this->isPublished = false;
    }
}
