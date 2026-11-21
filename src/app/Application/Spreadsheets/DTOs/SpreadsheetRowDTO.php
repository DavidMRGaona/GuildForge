<?php

declare(strict_types=1);

namespace App\Application\Spreadsheets\DTOs;

final readonly class SpreadsheetRowDTO
{
    /**
     * @param  int  $number  The row number as a spreadsheet program shows it (1 is the first row of the file)
     * @param  array<string, string>  $values  Text of each header, in header order; '' for a missing or empty cell
     */
    public function __construct(
        public int $number,
        public array $values,
    ) {}

    public function value(string $header): string
    {
        return $this->values[$header] ?? '';
    }
}
