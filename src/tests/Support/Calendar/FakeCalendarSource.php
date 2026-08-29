<?php

declare(strict_types=1);

namespace Tests\Support\Calendar;

use App\Application\Calendar\Contracts\CalendarEntrySourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;

/**
 * Configurable calendar source used in tests to simulate a module registering
 * entries through the CalendarSourceRegistry / CalendarAggregatorService.
 */
final class FakeCalendarSource implements CalendarEntrySourceInterface
{
    /**
     * @param  array<CalendarEntryDTO>  $entries
     */
    public function __construct(
        private readonly array $entries = [],
    ) {}

    public function sourceType(): string
    {
        return 'fake';
    }

    public function sourceLabel(): string
    {
        return 'Fake source';
    }

    public function color(): CalendarSourceColor
    {
        return CalendarSourceColor::Accent;
    }

    /**
     * @return array<CalendarEntryDTO>
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->entries;
    }
}
