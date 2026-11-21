<?php

declare(strict_types=1);

namespace App\Domain\Spreadsheets\Exceptions;

use RuntimeException;

/**
 * A valid template that could not be written: its temporary folder could not be created or
 * written (storage full or not writable), or openspout failed. It carries no previous exception.
 */
final class SpreadsheetWriteException extends RuntimeException
{
    public static function failed(): self
    {
        return new self('The spreadsheet could not be written');
    }
}
