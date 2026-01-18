<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Exceptions\CannotPublishPastEventException;
use App\Domain\ValueObjects\EventId;
use App\Domain\ValueObjects\Price;
use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;

final class Event
{
    public function __construct(
        private readonly EventId $id,
        private readonly string $title,
        private readonly Slug $slug,
        private readonly string $description,
        private readonly DateTimeImmutable $startDate,
        private readonly ?DateTimeImmutable $endDate = null,
        private readonly ?string $location = null,
        private readonly ?string $imagePublicId = null,
        private readonly ?Price $memberPrice = null,
        private readonly ?Price $nonMemberPrice = null,
        private bool $isPublished = false,
        private readonly ?DateTimeImmutable $createdAt = null,
        private readonly ?DateTimeImmutable $updatedAt = null,
    ) {
    }

    public function id(): EventId
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

    public function description(): string
    {
        return $this->description;
    }

    public function startDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function imagePublicId(): ?string
    {
        return $this->imagePublicId;
    }

    public function memberPrice(): ?Price
    {
        return $this->memberPrice;
    }

    public function nonMemberPrice(): ?Price
    {
        return $this->nonMemberPrice;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
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
        if ($this->isPast()) {
            throw CannotPublishPastEventException::create();
        }

        $this->isPublished = true;
    }

    public function unpublish(): void
    {
        $this->isPublished = false;
    }

    public function isUpcoming(): bool
    {
        return ($this->endDate ?? $this->startDate) > new DateTimeImmutable();
    }

    public function isPast(): bool
    {
        return ($this->endDate ?? $this->startDate) < new DateTimeImmutable();
    }

    public function isMultiDay(): bool
    {
        return $this->endDate !== null && $this->endDate > $this->startDate;
    }

    public function isFree(): bool
    {
        return $this->memberPrice === null && $this->nonMemberPrice === null;
    }
}
