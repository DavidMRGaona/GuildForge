<?php

declare(strict_types=1);

namespace App\Application\Spreadsheets;

use App\Application\Spreadsheets\DTOs\SpreadsheetDTO;
use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;

/**
 * Reads uploaded .csv and .xlsx files for modules, which have no Composer dependencies of their
 * own. The type comes from the content, never from the extension. Every value arrives as text:
 * .xlsx dates as YYYY-MM-DD (YYYY-MM-DD HH:MM:SS with a time), numbers without regional format
 * (1234.5), booleans as 1 or 0 and formulas as their stored result; .csv text as written.
 * Docs: docs/modules/spreadsheets.md.
 */
interface SpreadsheetReaderInterface
{
    /**
     * @param  string  $path  A local file, e.g. an UploadedFile's getRealPath()
     *
     * @throws SpreadsheetReadException with a closed reason; it never carries the file's content
     */
    public function read(string $path, SpreadsheetLimitsDTO $limits = new SpreadsheetLimitsDTO): SpreadsheetDTO;
}
