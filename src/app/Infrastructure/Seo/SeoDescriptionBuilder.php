<?php

declare(strict_types=1);

namespace App\Infrastructure\Seo;

use App\Application\Seo\DTOs\SeoScheduleDTO;
use Carbon\CarbonImmutable;

/**
 * Builds the plain-text description shown in link previews: an optional
 * "date, time · place" prefix followed by the page text, cut to 160 chars.
 */
final readonly class SeoDescriptionBuilder
{
    public const int MAX_LENGTH = 160;

    private const string SEPARATOR = ' · ';

    public function __construct(
        private string $timezone,
    ) {}

    public function build(?string $text, ?SeoScheduleDTO $schedule, string $fallback): string
    {
        $body = $this->plainText($text);

        if ($body === '') {
            $body = $this->plainText($fallback);
        }

        $parts = [];

        if ($schedule !== null) {
            $parts[] = $this->formatSchedule($schedule);

            $location = $this->plainText($schedule->location);
            if ($location !== '') {
                $parts[] = $location;
            }
        }

        if ($body !== '') {
            $parts[] = $body;
        }

        return $this->truncate(implode(self::SEPARATOR, $parts));
    }

    public function plainText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        // Pad tags with a space so "<p>Uno</p><p>Dos</p>" does not become "UnoDos".
        $text = strip_tags(str_replace('<', ' <', $html));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function formatSchedule(SeoScheduleDTO $schedule): string
    {
        $locale = app()->getLocale();
        $start = CarbonImmutable::instance($schedule->start)->setTimezone($this->timezone)->locale($locale);
        $end = $schedule->end !== null
            ? CarbonImmutable::instance($schedule->end)->setTimezone($this->timezone)->locale($locale)
            : null;

        $dateFormat = $this->translate('seo.formats.date');

        if ($end === null || $start->isSameDay($end)) {
            return $this->translate('seo.schedule.single_day', [
                'date' => $start->translatedFormat($dateFormat),
                'time' => $start->format('H:i'),
            ]);
        }

        if ($start->isSameMonth($end)) {
            return $this->translate('seo.schedule.same_month', [
                'start_day' => $start->format('j'),
                'end_date' => $end->translatedFormat($dateFormat),
            ]);
        }

        if ($start->isSameYear($end)) {
            return $this->translate('seo.schedule.same_year', [
                'start_day_month' => $start->translatedFormat($this->translate('seo.formats.day_month')),
                'end_date' => $end->translatedFormat($dateFormat),
            ]);
        }

        return $this->translate('seo.schedule.different_year', [
            'start_date' => $start->translatedFormat($dateFormat),
            'end_date' => $end->translatedFormat($dateFormat),
        ]);
    }

    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::MAX_LENGTH - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > intdiv(self::MAX_LENGTH, 2)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        // Regex instead of rtrim(): rtrim works on bytes and would split "·" or "–".
        return (string) preg_replace('/[\s.,;:·–-]+$/u', '', $cut).'…';
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function translate(string $key, array $replace = []): string
    {
        $value = __($key, $replace);

        return is_string($value) ? $value : $key;
    }
}
