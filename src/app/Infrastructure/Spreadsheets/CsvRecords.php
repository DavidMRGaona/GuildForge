<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Domain\Spreadsheets\SpreadsheetReadFailure;
use Generator;

/**
 * The records of a CSV text, as RFC 4180 reads them: a field is quoted only when it starts with «"»,
 * «""» inside it is one quote, and quoted fields may hold separators and line breaks; a quote in
 * the middle of an unquoted field is text. The same code counts and splits, so the field cap holds
 * for exactly what is read, and it is checked before a record grows past it.
 */
final class CsvRecords
{
    /**
     * @param  string  $text  UTF-8, without BOM
     * @param  int  $maxFields  Fields a record may have, empty ones included
     * @param  int  $limit  The caller's column limit, reported in the exception
     * @return Generator<int, list<string>> Records keyed by their 1-based number, blank ones included
     */
    public static function read(string $text, string $delimiter, int $maxFields, int $limit): Generator
    {
        $length = strlen($text);
        $unquotedStops = $delimiter."\r\n";
        $position = 0;
        $number = 0;

        while ($position < $length) {
            $number++;
            $fields = [];

            while (true) {
                if (count($fields) >= $maxFields) {
                    throw new SpreadsheetReadException(SpreadsheetReadFailure::TooManyColumns, limit: $limit);
                }

                $value = '';

                if ($position < $length && $text[$position] === '"') {
                    $position++;

                    while (true) {
                        $quote = strpos($text, '"', $position);

                        if ($quote === false) {
                            $value .= substr($text, $position);
                            $position = $length;

                            break;
                        }

                        $value .= substr($text, $position, $quote - $position);
                        $position = $quote + 1;

                        if ($position < $length && $text[$position] === '"') {
                            $value .= '"';
                            $position++;

                            continue;
                        }

                        break;
                    }
                }

                // Unquoted text, or whatever follows a closing quote up to the separator.
                $span = strcspn($text, $unquotedStops, $position);
                $value .= substr($text, $position, $span);
                $position += $span;
                $fields[] = $value;

                if ($position >= $length) {
                    break;
                }

                if ($text[$position] === $delimiter) {
                    $position++;

                    continue;
                }

                $position += $text[$position] === "\r" && ($text[$position + 1] ?? '') === "\n" ? 2 : 1;

                break;
            }

            yield $number => $fields;
        }
    }
}
