<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;

final readonly class SlugRedirect
{
    public function __construct(
        private string $id,
        private Slug $oldSlug,
        private Slug $newSlug,
        private string $entityType,
        private string $entityId,
        private DateTimeImmutable $createdAt,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function oldSlug(): Slug
    {
        return $this->oldSlug;
    }

    public function newSlug(): Slug
    {
        return $this->newSlug;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): string
    {
        return $this->entityId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function pointsToSameSlug(): bool
    {
        return $this->oldSlug->equals($this->newSlug);
    }

    public function updateTarget(Slug $newSlug): self
    {
        return new self(
            $this->id,
            $this->oldSlug,
            $newSlug,
            $this->entityType,
            $this->entityId,
            $this->createdAt,
        );
    }
}
