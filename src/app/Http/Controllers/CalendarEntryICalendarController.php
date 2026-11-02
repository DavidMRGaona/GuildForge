<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\Calendar\Services\ICalendarWriterInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * One public calendar entry as an iCalendar file, for people to add it to their
 * own calendar. Only entries the public calendar shows are served; anything
 * else (unknown source, source without lookup, hidden entry) answers 404.
 */
final class CalendarEntryICalendarController extends Controller
{
    public function __construct(
        private readonly CalendarAggregatorServiceInterface $calendar,
        private readonly ICalendarWriterInterface $writer,
    ) {}

    public function __invoke(string $source, string $id): Response
    {
        $entry = $this->calendar->findEntry($source, $id);

        if (! $entry instanceof CalendarEntryDTO) {
            abort(404);
        }

        return response($this->writer->write([$entry]), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename='.$this->fileName($entry),
        ]);
    }

    private function fileName(CalendarEntryDTO $entry): string
    {
        $slug = Str::slug($entry->title);

        return ($slug === '' ? 'calendar' : $slug).'.ics';
    }
}
