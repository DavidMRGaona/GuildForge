<?php

declare(strict_types=1);

namespace App\Application\Spreadsheets\DTOs;

final readonly class SpreadsheetRowDTO
{
    /**
     * @param  int  $number  The row number as a spreadsheet program shows it (1 is the first row of the file)
     * @param  array<array-key, string>  $values  Text of each header, in header order; '' for a missing or empty cell.
     *                                            PHP turns a numeric header («2024») into an int key: look values up with value()
     */
    public function __construct(
        public int $number,
        public array $values,
    ) {}

    /**
     * The text under a header as it was written in the file; null when the file has no such header.
     */
    public function value(string $header): ?string
    {
        return $this->values[$header] ?? null;
    }
}
