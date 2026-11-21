<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

use DateInterval;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;

/**
 * The text of a cell, so callers never guess: dates as YYYY-MM-DD (with HH:MM:SS when they have a
 * time), times without a date as HH:MM:SS, numbers with a dot and no thousands separator, booleans as 1 or 0, formulas as their
 * stored result, texts starting with «=» as written and error cells (#N/A) as empty. Surrounding
 * spaces are trimmed.
 */
final class CellText
{
    /** Significant digits kept from a float, enough for what people type and free of binary noise. */
    private const int SIGNIFICANT_DIGITS = 14;

    private const string EPOCH_1900 = '1899-12-30';

    private const string EPOCH_1904 = '1904-01-01';

    public function of(Cell $cell): string
    {
        if ($cell instanceof ErrorCell) {
            return '';
        }

        if ($cell instanceof FormulaCell) {
            // openspout also reads a text starting with «=» as a formula, without a stored result. A real
            // formula always has one, except when it is an error: then the formula arrives as written.
            $result = $cell->getComputedValue();

            return $this->text($result ?? $cell->getValue());
        }

        return $this->text($cell->getValue());
    }

    private function text(mixed $value): string
    {
        return match (true) {
            is_string($value) => $this->trim($value),
            is_bool($value) => $value ? '1' : '0',
            is_int($value) => (string) $value,
            is_float($value) => $this->number($value),
            $value instanceof DateTimeInterface => $this->dateTime($value),
            $value instanceof DateInterval => $this->duration($value),
            default => '',
        };
    }

    public function ofText(string $text): string
    {
        return $this->trim($text);
    }

    /**
     * A time without a date is a number below 1, which openspout reads as a time on the epoch day:
     * 1899-12-30 (any time) or, in 1904 workbooks, 1904-01-01 (a time other than midnight).
     */
    private function dateTime(DateTimeInterface $value): string
    {
        $day = $value->format('Y-m-d');
        $time = $value->format('H:i:s');

        if ($day === self::EPOCH_1900 || ($day === self::EPOCH_1904 && $time !== '00:00:00')) {
            return $time;
        }

        return $time === '00:00:00' ? $day : $day.' '.$time;
    }

    private function trim(string $text): string
    {
        return preg_replace('/^[\s\p{Z}\x{FEFF}\x{200B}]+|[\s\p{Z}\x{FEFF}\x{200B}]+$/u', '', $text) ?? trim($text);
    }

    private function number(float $value): string
    {
        if (! is_finite($value)) {
            return '';
        }

        if ($value === 0.0) {
            return '0';
        }

        $magnitude = (int) floor(log10(abs($value)));
        $decimals = max(0, min(20, self::SIGNIFICANT_DIGITS - $magnitude - 1));
        $text = sprintf('%.'.$decimals.'F', round($value, $decimals));

        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return $text === '-0' ? '0' : $text;
    }

    private function duration(DateInterval $interval): string
    {
        $hours = ($interval->days !== false ? $interval->days : $interval->d) * 24 + $interval->h;

        return sprintf('%02d:%02d:%02d', $hours, $interval->i, $interval->s);
    }
}
