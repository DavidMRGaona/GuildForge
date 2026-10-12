<?php

declare(strict_types=1);

namespace App\Application\Seo\DTOs;

use DateTimeInterface;

/**
 * When and where something happens. Pages that carry one get a date/time/place
 * prefix in their description and an Event JSON-LD block.
 */
final readonly class SeoScheduleDTO
{
    public function __construct(
        public DateTimeInterface $start,
        public ?DateTimeInterface $end = null,
        public ?string $location = null,
        public ?float $price = null,
    ) {}
}
