<?php

declare(strict_types=1);

namespace App\Application\Calendar\Contracts;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;

interface CalendarEntrySourceInterface
{
    /**
     * Unique identifier for this source's entry type (e.g. 'event', 'game-table').
     */
    public function sourceType(): string;

    /**
     * Human-readable, translated label for this source.
     */
    public function sourceLabel(): string;

    /**
     * Semantic color token used to render entries from this source.
     */
    public function color(): CalendarSourceColor;

    /**
     * Find calendar entries within the given date range.
     *
     * The range is [from, to): the end is exclusive. Both bounds are instants in the
     * storage timezone, as stored values are compared against them directly.
     *
     * @return array<CalendarEntryDTO>
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
