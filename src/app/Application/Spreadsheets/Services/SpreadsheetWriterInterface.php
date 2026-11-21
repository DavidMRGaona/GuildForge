<?php

declare(strict_types=1);

namespace App\Application\Spreadsheets\Services;

use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetWriteException;

/**
 * Writes one-sheet .xlsx templates for modules (the format a file to import must follow): the
 * header in bold and kept in view, columns wide enough for their text. Docs: docs/modules/spreadsheets.md.
 */
interface SpreadsheetWriterInterface
{
    /**
     * @return string The .xlsx bytes, e.g. for response()->streamDownload()
     *
     * @throws SpreadsheetWriteException when the temporary file cannot be written
     */
    public function write(SpreadsheetTemplateDTO $template): string;
}
