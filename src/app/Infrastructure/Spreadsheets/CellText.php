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
 * time), numbers with a dot and no thousands separator, booleans as 1 or 0, formulas as their
 * stored result, texts starting with «=» as written and error cells (#N/A) as empty. Surrounding
 * spaces are trimmed.
 */
final class CellText
{
    /** Significant digits kept from a float, enough for what people type and free of binary noise. */
    private const int SIGNIFICANT_DIGITS = 14;

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
            $value instanceof DateTimeInterface => $value->format($value->format('His') === '000000' ? 'Y-m-d' : 'Y-m-d H:i:s'),
            $value instanceof DateInterval => $this->duration($value),
            default => '',
        };
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
