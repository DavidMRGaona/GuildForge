<?php

declare(strict_types=1);

namespace App\Domain\Spreadsheets\Exceptions;

use App\Domain\Spreadsheets\SpreadsheetReadFailure;
use RuntimeException;

/**
 * A spreadsheet that could not be read. It never carries the file's content: neither in the
 * message nor through a previous exception, whose message or trace could hold it.
 */
final class SpreadsheetReadException extends RuntimeException
{
    /**
     * @param  int|null  $limit  The limit passed (rows, columns or bytes) for the too_* reasons
     * @param  int|null  $column  The 1-based column of the second header, for duplicate_headers
     */
    public function __construct(
        public readonly SpreadsheetReadFailure $reason,
        public readonly ?int $limit = null,
        public readonly ?int $column = null,
    ) {
        parent::__construct('The spreadsheet could not be read: '.$reason->value);
    }
}
