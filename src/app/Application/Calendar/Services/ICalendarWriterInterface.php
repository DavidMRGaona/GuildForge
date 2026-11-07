<?php

declare(strict_types=1);

namespace App\Application\Calendar\Services;

use App\Application\Calendar\DTOs\CalendarEntryDTO;

interface ICalendarWriterInterface
{
    /**
     * Write the entries as an iCalendar document (RFC 5545, METHOD:PUBLISH),
     * one VEVENT per entry, with instants in UTC.
     *
     * A calendar name makes it a subscription feed: the document then carries the
     * name (`NAME`, `X-WR-CALNAME`) and asks clients to refresh it every hour.
     *
     * @param  array<CalendarEntryDTO>  $entries
     */
    public function write(array $entries, ?string $calendarName = null): string;
}
