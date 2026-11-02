<?php

declare(strict_types=1);

namespace App\Application\Calendar\Services;

use App\Application\Calendar\DTOs\AddToCalendarLinksDTO;
use App\Application\Calendar\DTOs\CalendarEntryDTO;

interface AddToCalendarLinksFactoryInterface
{
    /**
     * Links that add the entry to a personal calendar: Google Calendar and
     * Outlook prefilled forms, and the iCalendar download when the entry's
     * source can find it by id (CalendarEntryLookupInterface).
     */
    public function for(CalendarEntryDTO $entry): AddToCalendarLinksDTO;
}
