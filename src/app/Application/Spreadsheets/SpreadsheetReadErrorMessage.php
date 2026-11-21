<?php

declare(strict_types=1);

namespace App\Application\Spreadsheets;

use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Domain\Spreadsheets\SpreadsheetReadFailure;

/**
 * The message to show the person who uploaded the file, in the current locale.
 */
final class SpreadsheetReadErrorMessage
{
    public static function for(SpreadsheetReadException $exception): string
    {
        $limit = $exception->limit ?? 0;

        return __('spreadsheets.errors.'.$exception->reason->value, [
            'limit' => $exception->reason === SpreadsheetReadFailure::TooLarge ? self::bytes($limit) : (string) $limit,
            'column' => (string) ($exception->column ?? 0),
        ]);
    }

    private static function bytes(int $bytes): string
    {
        $megabyte = 1024 * 1024;

        if ($bytes >= $megabyte) {
            $megabytes = round($bytes / $megabyte, 1);

            return ($megabytes === floor($megabytes) ? (string) (int) $megabytes : (string) $megabytes).' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }
}
