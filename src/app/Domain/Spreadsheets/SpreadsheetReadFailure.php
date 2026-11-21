<?php

declare(strict_types=1);

namespace App\Domain\Spreadsheets;

/**
 * Why a spreadsheet could not be read. The values are stable: modules may store or compare them.
 */
enum SpreadsheetReadFailure: string
{
    /** Neither a CSV text nor an Office Open XML workbook (.xls, .ods, .docx, images, PDF...). */
    case UnsupportedType = 'unsupported_type';

    /** Missing, damaged or in an encoding that cannot be decoded. */
    case Unreadable = 'unreadable';

    /** No header or no row with data below it. */
    case Empty = 'empty';

    /** More data rows than the caller's limit. */
    case TooManyRows = 'too_many_rows';

    /** A header or a row wider than the caller's limit. */
    case TooManyColumns = 'too_many_columns';

    /** More bytes than the caller's limit (for an .xlsx, also once uncompressed). */
    case TooLarge = 'too_large';

    /** Two headers that match ignoring case, accents and spacing. */
    case DuplicateHeaders = 'duplicate_headers';
}
