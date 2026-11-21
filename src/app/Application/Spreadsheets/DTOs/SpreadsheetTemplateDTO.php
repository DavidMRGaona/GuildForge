<?php

declare(strict_types=1);

namespace App\Application\Spreadsheets\DTOs;

use App\Application\Spreadsheets\SpreadsheetHeader;
use InvalidArgumentException;

/**
 * A one-sheet workbook to download: a header and, optionally, example rows. Every text is written
 * as text, never as a formula, so values that start with «=» are safe.
 */
final readonly class SpreadsheetTemplateDTO
{
    private const int MAX_SHEET_NAME_LENGTH = 31;

    /** Characters a spreadsheet cell holds at most (Excel and LibreOffice). */
    public const int MAX_CELL_LENGTH = 32767;

    /**
     * @param  list<string>  $headers  Non-blank and different from each other (see SpreadsheetHeader)
     * @param  list<list<string|int|float|null>>  $rows  No wider than the header; null leaves the cell empty.
     *                                                   Texts (headers too) in UTF-8, up to 32767 characters; finite numbers
     * @param  string  $sheetName  1 to 31 characters, without \ / ? * [ ] : and not starting or ending with '
     */
    public function __construct(
        public array $headers,
        public array $rows = [],
        public string $sheetName = 'Hoja1',
    ) {
        $this->assertHeaders($headers);
        $this->assertRows($rows, count($headers));
        $this->assertSheetName($sheetName);
    }

    /**
     * @param  array<mixed>  $headers
     */
    private function assertHeaders(array $headers): void
    {
        if ($headers === [] || ! array_is_list($headers)) {
            throw new InvalidArgumentException('A spreadsheet template needs a list of headers');
        }

        $seen = [];

        foreach ($headers as $header) {
            if (! is_string($header) || trim($header) === '') {
                throw new InvalidArgumentException('Spreadsheet template headers must be non-blank texts');
            }

            $this->assertText($header);

            $key = SpreadsheetHeader::normalise($header);

            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Spreadsheet template headers must be different from each other');
            }

            $seen[$key] = true;
        }
    }

    /**
     * @param  array<mixed>  $rows
     */
    private function assertRows(array $rows, int $width): void
    {
        if (! array_is_list($rows)) {
            throw new InvalidArgumentException('Spreadsheet template rows must be a list');
        }

        foreach ($rows as $row) {
            if (! is_array($row) || ! array_is_list($row) || count($row) > $width) {
                throw new InvalidArgumentException('Each spreadsheet template row must be a list no wider than the header');
            }

            foreach ($row as $value) {
                if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value)) {
                    throw new InvalidArgumentException('Spreadsheet template values must be texts, numbers or null');
                }

                if (is_float($value) && ! is_finite($value)) {
                    throw new InvalidArgumentException('Spreadsheet template numbers must be finite');
                }

                if (is_string($value)) {
                    $this->assertText($value);
                }
            }
        }
    }

    private function assertText(string $text): void
    {
        if (! mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > self::MAX_CELL_LENGTH) {
            throw new InvalidArgumentException('Spreadsheet template texts must be UTF-8 and fit in a cell');
        }
    }

    private function assertSheetName(string $name): void
    {
        if (! mb_check_encoding($name, 'UTF-8')) {
            throw new InvalidArgumentException('Invalid spreadsheet sheet name');
        }

        $length = mb_strlen($name);

        if ($length < 1
            || $length > self::MAX_SHEET_NAME_LENGTH
            || strpbrk($name, '\\/?*[]:') !== false
            || str_starts_with($name, "'")
            || str_ends_with($name, "'")) {
            throw new InvalidArgumentException('Invalid spreadsheet sheet name');
        }
    }
}
