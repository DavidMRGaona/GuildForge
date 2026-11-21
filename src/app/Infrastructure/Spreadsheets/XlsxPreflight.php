<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Domain\Spreadsheets\SpreadsheetReadFailure;
use ZipArchive;

/**
 * Checks an .xlsx before openspout opens it, since openspout builds a whole row in memory and fills
 * the gaps up to its last cell reference: every entry is inflated once and its real bytes counted
 * (the sizes the archive declares can lie), and no row of any worksheet may reach past the last
 * column a spreadsheet has, whether through a cell reference or through cells without one.
 */
final class XlsxPreflight
{
    /** An .xlsx may take at most this many times maxBytes once uncompressed (a ZIP bomb guard). */
    public const int MAX_EXPANSION = 30;

    /** Columns of Excel and LibreOffice (A to XFD). */
    public const int MAX_SHEET_COLUMNS = 16384;

    private const int CHUNK_BYTES = 65536;

    /** A single tag longer than this is no worksheet. */
    private const int MAX_TAG_BYTES = 1048576;

    private const string WORKSHEET_PATTERN = '#^xl/worksheets/[^/]+\.xml$#';

    /** `<row …>` and `<c …>` start tags, with or without a prefix; quoted values may hold `>`. */
    private const string TAG_PATTERN = '/<(?:[A-Za-z_][\w.-]*:)?(row|c)(?=[\s\/>])((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/';

    private const string REFERENCE_PATTERN = '/(?:^|\s)r\s*=\s*(["\'])([A-Za-z]+)\d+\1/';

    public function check(string $path, SpreadsheetLimitsDTO $limits): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::UnsupportedType);
            }

            $budget = $limits->maxBytes * self::MAX_EXPANSION;
            $maxColumns = max(self::MAX_SHEET_COLUMNS, $limits->maxColumns);

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if ($name === false) {
                    throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
                }

                $budget -= $this->inflate($zip, $name, $budget, $limits, $maxColumns);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @return int The bytes the entry really takes
     */
    private function inflate(ZipArchive $zip, string $name, int $budget, SpreadsheetLimitsDTO $limits, int $maxColumns): int
    {
        $stream = $zip->getStream($name);

        if ($stream === false) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        $isWorksheet = preg_match(self::WORKSHEET_PATTERN, $name) === 1;
        $inflated = 0;
        $pending = '';
        $rowWidth = 0;

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, self::CHUNK_BYTES);

                if ($chunk === false) {
                    throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
                }

                $inflated += strlen($chunk);

                if ($inflated > $budget) {
                    throw new SpreadsheetReadException(SpreadsheetReadFailure::TooLarge, limit: $limits->maxBytes);
                }

                if ($isWorksheet) {
                    $pending = $this->scan($pending.$chunk, $rowWidth, $limits, $maxColumns, final: false);
                }
            }

            if ($isWorksheet) {
                $this->scan($pending, $rowWidth, $limits, $maxColumns, final: true);
            }
        } finally {
            fclose($stream);
        }

        return $inflated;
    }

    /**
     * Follows the width of the current row through the complete tags of the buffer.
     *
     * @return string The tail after the last «<», which may hold a tag cut by the chunk ('' when final)
     */
    private function scan(string $buffer, int &$rowWidth, SpreadsheetLimitsDTO $limits, int $maxColumns, bool $final): string
    {
        $cut = $final ? false : strrpos($buffer, '<');
        $complete = $cut === false ? $buffer : substr($buffer, 0, $cut);
        $tail = $cut === false ? '' : substr($buffer, $cut);

        if (strlen($tail) > self::MAX_TAG_BYTES) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        preg_match_all(self::TAG_PATTERN, $complete, $tags, PREG_SET_ORDER);

        foreach ($tags as $tag) {
            if ($tag[1] === 'row') {
                $rowWidth = 0;

                continue;
            }

            // A cell without a reference takes the column after the previous one.
            $rowWidth = preg_match(self::REFERENCE_PATTERN, $tag[2], $reference) === 1
                ? max($rowWidth, $this->columnIndex($reference[2], $maxColumns))
                : $rowWidth + 1;

            if ($rowWidth > $maxColumns) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::TooManyColumns, limit: $limits->maxColumns);
            }
        }

        return $tail;
    }

    /**
     * The 1-based column of a reference's letters, or past the limit as soon as it is.
     */
    private function columnIndex(string $letters, int $maxColumns): int
    {
        $index = 0;

        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = $index * 26 + (ord($letter[0]) - ord('A') + 1);

            if ($index > $maxColumns) {
                return $maxColumns + 1;
            }
        }

        return $index;
    }
}
