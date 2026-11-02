<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Export;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * A calendar entry as personal calendars receive it: instants in UTC, an end
 * even when the source gives none, an absolute page URL and plain text.
 */
final readonly class ExportableCalendarEntry
{
    /** Same default as the public calendar (FullCalendar's) for entries without end. */
    private const string DEFAULT_DURATION = 'PT1H';

    private function __construct(
        public string $uid,
        public string $title,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public string $url,
        public ?string $location,
        public string $description,
    ) {}

    public static function from(CalendarEntryDTO $entry, UrlGenerator $urls): self
    {
        $utc = new DateTimeZone('UTC');
        $start = $entry->start->setTimezone($utc);
        $end = $entry->end?->setTimezone($utc) ?? $start->add(new DateInterval(self::DEFAULT_DURATION));
        $host = parse_url($urls->to('/'), PHP_URL_HOST);
        $location = $entry->details['location'] ?? null;
        $description = $entry->details['description'] ?? null;

        return new self(
            uid: $entry->sourceType.'-'.$entry->id.'@'.(is_string($host) && $host !== '' ? $host : 'localhost'),
            title: trim($entry->title),
            start: $start,
            end: $end,
            url: $urls->to($entry->url),
            location: is_string($location) && trim($location) !== '' ? trim($location) : null,
            description: is_string($description) ? self::plainText($description) : '',
        );
    }

    /**
     * Rich text turned into plain text: block ends become line breaks, tags go
     * and entities are decoded.
     */
    private static function plainText(string $html): string
    {
        $text = (string) preg_replace('#<br\s*/?>|</(p|div|li|h[1-6]|blockquote)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_map(trim(...), preg_split('/\R/u', $text) ?: []);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }
}
