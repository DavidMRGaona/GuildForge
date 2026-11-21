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

    /**
     * What to tell the person who uploaded the file, in the current locale, with the limit
     * (`2 MB`, `1000`) or the column number.
     */
    public function userMessage(): string
    {
        $limit = $this->limit ?? 0;

        return __('spreadsheets.errors.'.$this->reason->value, [
            'limit' => $this->reason === SpreadsheetReadFailure::TooLarge ? $this->bytes($limit) : (string) $limit,
            'column' => (string) ($this->column ?? 0),
        ]);
    }

    private function bytes(int $bytes): string
    {
        $megabyte = 1024 * 1024;

        if ($bytes >= $megabyte) {
            $megabytes = round($bytes / $megabyte, 1);

            return ($megabytes === floor($megabytes) ? (string) (int) $megabytes : (string) $megabytes).' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }
}
