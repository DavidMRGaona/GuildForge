<?php

declare(strict_types=1);

namespace App\Domain\Spreadsheets\Exceptions;

use RuntimeException;

/**
 * A valid template that could not be written (temporary storage full or not writable).
 */
final class SpreadsheetWriteException extends RuntimeException
{
    public static function failed(): self
    {
        return new self('The spreadsheet could not be written');
    }
}
