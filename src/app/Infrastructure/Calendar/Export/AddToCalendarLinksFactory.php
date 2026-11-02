<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Export;

use App\Application\Calendar\DTOs\AddToCalendarLinksDTO;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\AddToCalendarLinksFactoryInterface;
use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Str;

final readonly class AddToCalendarLinksFactory implements AddToCalendarLinksFactoryInterface
{
    private const string GOOGLE = 'https://calendar.google.com/calendar/render';

    private const string OUTLOOK = 'https://outlook.live.com/calendar/0/deeplink/compose';

    /** Keeps the prefilled links well under the URL lengths browsers and providers accept. */
    private const int DETAILS_CHARACTERS = 500;

    public function __construct(
        private UrlGenerator $urls,
        private CalendarAggregatorServiceInterface $calendar,
    ) {}

    public function for(CalendarEntryDTO $entry): AddToCalendarLinksDTO
    {
        $exportable = ExportableCalendarEntry::from($entry, $this->urls);
        $details = Str::limit($exportable->description, self::DETAILS_CHARACTERS);
        $details = $details === '' ? $exportable->url : $details."\n\n".$exportable->url;

        return new AddToCalendarLinksDTO(
            google: $this->link(self::GOOGLE, [
                'action' => 'TEMPLATE',
                'text' => $exportable->title,
                'dates' => $exportable->start->format('Ymd\THis\Z').'/'.$exportable->end->format('Ymd\THis\Z'),
                'details' => $details,
                'location' => $exportable->location,
            ]),
            outlook: $this->link(self::OUTLOOK, [
                'path' => '/calendar/action/compose',
                'rru' => 'addevent',
                'subject' => $exportable->title,
                'startdt' => $exportable->start->format('Y-m-d\TH:i:s\Z'),
                'enddt' => $exportable->end->format('Y-m-d\TH:i:s\Z'),
                'body' => $details,
                'location' => $exportable->location,
            ]),
            ics: $this->calendar->canFindEntries($entry->sourceType)
                ? $this->urls->route('calendar.entry.ics', ['source' => $entry->sourceType, 'id' => $entry->id])
                : null,
        );
    }

    /**
     * @param  array<string, string|null>  $query
     */
    private function link(string $base, array $query): string
    {
        $query = array_filter($query, fn (?string $value): bool => $value !== null);

        return $base.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
