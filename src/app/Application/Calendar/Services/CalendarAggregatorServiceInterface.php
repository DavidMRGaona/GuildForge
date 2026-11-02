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

    /**
     * Find one entry of the given source type, or null when the source does not
     * exist, cannot find entries by id (CalendarEntryLookupInterface), does not
     * show that entry or fails.
     */
    public function findEntry(string $sourceType, string $id): ?CalendarEntryDTO;

    /**
     * Whether the given source type can find its entries by id.
     */
    public function canFindEntries(string $sourceType): bool;
}
