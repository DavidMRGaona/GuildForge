<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Export;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\ICalendarWriterInterface;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Str;

final readonly class ICalendarWriter implements ICalendarWriterInterface
{
    /** RFC 5545 §3.1: content lines longer than this many octets are folded. */
    private const int LINE_OCTETS = 75;

    private const int DESCRIPTION_CHARACTERS = 2000;

    public function __construct(
        private UrlGenerator $urls,
    ) {}

    /**
     * @param  array<CalendarEntryDTO>  $entries
     */
    public function write(array $entries): string
    {
        $stamp = $this->instant(CarbonImmutable::now('UTC'));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//GuildForge//Calendar//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($entries as $entry) {
            $lines = [...$lines, ...$this->event(ExportableCalendarEntry::from($entry, $this->urls), $stamp)];
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * @return array<string>
     */
    private function event(ExportableCalendarEntry $entry, string $stamp): array
    {
        $description = Str::limit($entry->description, self::DESCRIPTION_CHARACTERS);
        $description = $description === '' ? $entry->url : $description."\n\n".$entry->url;

        return array_values(array_filter([
            'BEGIN:VEVENT',
            'UID:'.$entry->uid,
            'DTSTAMP:'.$stamp,
            'DTSTART:'.$this->instant($entry->start),
            'DTEND:'.$this->instant($entry->end),
            'SUMMARY:'.$this->text($entry->title),
            $entry->location !== null ? 'LOCATION:'.$this->text($entry->location) : null,
            'DESCRIPTION:'.$this->text($description),
            'URL:'.$entry->url,
            'END:VEVENT',
        ]));
    }

    private function instant(DateTimeInterface $instant): string
    {
        return $instant->format('Ymd\THis\Z');
    }

    /** RFC 5545 §3.3.11: TEXT values escape backslashes, semicolons, commas and line breaks. */
    private function text(string $value): string
    {
        $value = str_replace(['\\', ';', ','], ['\\\\', '\;', '\\,'], $value);

        return (string) preg_replace('/\R/u', '\\n', $value);
    }

    /** Folds a content line at 75 octets without splitting a UTF-8 character. */
    private function fold(string $line): string
    {
        if (strlen($line) <= self::LINE_OCTETS) {
            return $line;
        }

        $folded = [];
        $current = '';
        // Continuation lines start with a space, which counts towards their 75 octets.
        $limit = self::LINE_OCTETS;

        foreach (mb_str_split($line) as $character) {
            if (strlen($current) + strlen($character) > $limit) {
                $folded[] = $current;
                $current = '';
                $limit = self::LINE_OCTETS - 1;
            }

            $current .= $character;
        }

        $folded[] = $current;

        return implode("\r\n ", $folded);
    }
}
