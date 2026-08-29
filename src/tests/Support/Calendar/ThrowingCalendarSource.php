<?php

declare(strict_types=1);

namespace Tests\Support\Calendar;

use App\Application\Calendar\Contracts\CalendarEntrySourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;
use RuntimeException;

/**
 * Calendar source that always fails, used to verify the aggregator isolates
 * broken sources instead of letting the whole calendar endpoint break.
 */
final class ThrowingCalendarSource implements CalendarEntrySourceInterface
{
    public function sourceType(): string
    {
        return 'throwing';
    }

    public function sourceLabel(): string
    {
        return 'Throwing source';
    }

    public function color(): CalendarSourceColor
    {
        return CalendarSourceColor::Warning;
    }

    /**
     * @return array<CalendarEntryDTO>
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        throw new RuntimeException('Simulated calendar source failure.');
    }
}
