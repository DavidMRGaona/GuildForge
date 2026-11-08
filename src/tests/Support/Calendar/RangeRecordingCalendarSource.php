<?php

declare(strict_types=1);

namespace Tests\Support\Calendar;

use App\Application\Calendar\Contracts\CalendarEntrySourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;

/**
 * Calendar source with no entries that remembers the range it was last asked for
 * and how many times it was asked, to check windows and caching.
 */
final class RangeRecordingCalendarSource implements CalendarEntrySourceInterface
{
    public ?DateTimeImmutable $lastFrom = null;

    public ?DateTimeImmutable $lastTo = null;

    public int $calls = 0;

    public function sourceType(): string
    {
        return 'recording';
    }

    public function sourceLabel(): string
    {
        return 'Recording source';
    }

    public function color(): CalendarSourceColor
    {
        return CalendarSourceColor::Info;
    }

    /**
     * @return array<CalendarEntryDTO>
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $this->lastFrom = $from;
        $this->lastTo = $to;
        $this->calls++;

        return [];
    }
}
