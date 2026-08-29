<?php

declare(strict_types=1);

namespace App\Application\Calendar\Services;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use DateTimeImmutable;

interface CalendarAggregatorServiceInterface
{
    /**
     * Aggregate calendar entries from the core source and all registered
     * module sources within the given date range, sorted by start date.
     *
     * @return array<CalendarEntryDTO>
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
