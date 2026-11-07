<?php

declare(strict_types=1);

namespace App\Application\Calendar\Contracts;

use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use DateTimeImmutable;

/**
 * Tells the core which public calendar entries a person takes part in, for their
 * personal calendar (the "only mine" filter of `/calendario` and the private
 * subscription feed). Modules register it through
 * `ModuleServiceProvider::registerCalendarAttendanceSources()`.
 *
 * A reference may point at an entry of any source, including another module's or
 * the core's: a tournament participant gets `('event', $eventId)`. The core keeps
 * only the references the public calendar shows in the range, so returning a
 * hidden, cancelled or unknown entry is harmless.
 */
interface CalendarAttendanceSourceInterface
{
    /**
     * Entries the person is confirmed in or runs, whose time overlaps the range.
     *
     * The range is [from, to): the end is exclusive. Both bounds are instants in the
     * storage timezone, as in `CalendarEntrySourceInterface::findByDateRange()`.
     * Leave out what is pending, waitlisted, rejected or cancelled.
     *
     * @return array<CalendarEntryReferenceDTO>
     */
    public function attendedEntries(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
