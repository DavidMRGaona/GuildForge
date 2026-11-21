<?php

declare(strict_types=1);

namespace App\Application\Spreadsheets\DTOs;

use InvalidArgumentException;

/**
 * What a caller accepts. Reaching a limit stops reading at once: the rest of the file is never loaded.
 */
final readonly class SpreadsheetLimitsDTO
{
    public const int DEFAULT_MAX_ROWS = 1000;

    public const int DEFAULT_MAX_COLUMNS = 50;

    public const int DEFAULT_MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @param  int  $maxRows  Data rows below the header (blank rows do not count)
     * @param  int  $maxColumns  Width of the header and of every row, up to its last cell with text
     * @param  int  $maxBytes  Size of the file; an .xlsx may also expand up to a fixed multiple of it once uncompressed
     */
    public function __construct(
        public int $maxRows = self::DEFAULT_MAX_ROWS,
        public int $maxColumns = self::DEFAULT_MAX_COLUMNS,
        public int $maxBytes = self::DEFAULT_MAX_BYTES,
    ) {
        foreach (['maxRows' => $maxRows, 'maxColumns' => $maxColumns, 'maxBytes' => $maxBytes] as $name => $value) {
            if ($value < 1) {
                throw new InvalidArgumentException("The spreadsheet limit {$name} must be at least 1");
            }
        }
    }
}
