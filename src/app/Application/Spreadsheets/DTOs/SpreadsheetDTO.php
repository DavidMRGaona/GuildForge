<?php

declare(strict_types=1);

namespace App\Application\Spreadsheets\DTOs;

use App\Application\Spreadsheets\Enums\SpreadsheetFormat;

/**
 * The first sheet of a file: its header and at least one row with text below it.
 */
final readonly class SpreadsheetDTO
{
    /**
     * @param  list<string>  $headers  The first non-empty row, trimmed; columns without a header are left out
     * @param  non-empty-list<SpreadsheetRowDTO>  $rows  Rows with text under some header, blank ones skipped
     */
    public function __construct(
        public SpreadsheetFormat $format,
        public array $headers,
        public array $rows,
    ) {}
}
