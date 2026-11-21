<?php

declare(strict_types=1);

namespace App\Application\Spreadsheets;

use Illuminate\Support\Str;

/**
 * How headers are compared: ignoring case, accents and spacing, so «Teléfono», «TELEFONO» and
 * « telefono » are the same column. The reader refuses two such headers; modules may use it to
 * find their columns.
 */
final class SpreadsheetHeader
{
    public static function normalise(string $header): string
    {
        $spaced = trim((string) preg_replace('/[\s\p{Z}]+/u', ' ', $header));
        $ascii = Str::lower(trim(Str::ascii($spaced)));

        // Headers made only of characters without an ASCII form (emoji, other scripts) are compared as written.
        return $ascii !== '' ? $ascii : Str::lower($spaced);
    }
}
