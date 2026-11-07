<?php

declare(strict_types=1);

namespace Tests\Support\Calendar;

use App\Application\Calendar\Contracts\CalendarAttendanceSourceInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * Attendance source that always fails, to check a broken module never breaks
 * anyone's personal calendar.
 */
final class ThrowingCalendarAttendanceSource implements CalendarAttendanceSourceInterface
{
    public function attendedEntries(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        throw new RuntimeException('Simulated attendance source failure.');
    }
}
