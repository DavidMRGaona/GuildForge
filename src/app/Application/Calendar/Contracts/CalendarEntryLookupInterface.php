<?php

declare(strict_types=1);

namespace App\Application\Calendar\Contracts;

use App\Application\Calendar\DTOs\CalendarEntryDTO;

/**
 * Optional companion of CalendarEntrySourceInterface: a source that implements
 * it lets people download one of its entries as an iCalendar file
 * (`/calendario/{sourceType}/{id}.ics`) and get "add to calendar" links for it.
 */
interface CalendarEntryLookupInterface
{
    /**
     * Find one entry by the id this source gives it in `CalendarEntryDTO::$id`.
     *
     * Return only an entry the public calendar would show (published, not
     * cancelled...), and null for anything else, including ids that are not
     * well formed: the download answers 404 then.
     */
    public function findEntry(string $id): ?CalendarEntryDTO;
}
