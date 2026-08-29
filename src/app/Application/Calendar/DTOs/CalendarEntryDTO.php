<?php

declare(strict_types=1);

namespace App\Application\Calendar\DTOs;

use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;

final readonly class CalendarEntryDTO
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $id,
        public string $sourceType,
        public string $sourceLabel,
        public CalendarSourceColor $color,
        public string $title,
        public DateTimeImmutable $start,
        public ?DateTimeImmutable $end,
        public string $url,
        public array $details = [],
    ) {}
}
