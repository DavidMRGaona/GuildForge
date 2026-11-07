<?php

declare(strict_types=1);

namespace Tests\Support\Calendar;

use App\Application\Calendar\Contracts\CalendarAttendanceSourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use DateTimeImmutable;

/**
 * Attendance source used in tests: answers the references given per user id and
 * remembers the last range it was asked about.
 */
final class FakeCalendarAttendanceSource implements CalendarAttendanceSourceInterface
{
    public ?DateTimeImmutable $lastFrom = null;

    public ?DateTimeImmutable $lastTo = null;

    /**
     * @param  array<string, array<CalendarEntryReferenceDTO>>  $referencesByUser
     */
    public function __construct(
        private readonly array $referencesByUser = [],
    ) {}

    /**
     * @return array<CalendarEntryReferenceDTO>
     */
    public function attendedEntries(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $this->lastFrom = $from;
        $this->lastTo = $to;

        return $this->referencesByUser[$userId] ?? [];
    }
}
