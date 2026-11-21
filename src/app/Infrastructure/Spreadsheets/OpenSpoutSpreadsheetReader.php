<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

use App\Application\Spreadsheets\DTOs\SpreadsheetDTO;
use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use App\Application\Spreadsheets\DTOs\SpreadsheetRowDTO;
use App\Application\Spreadsheets\Enums\SpreadsheetFormat;
use App\Application\Spreadsheets\SpreadsheetHeader;
use App\Application\Spreadsheets\SpreadsheetReaderInterface;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Domain\Spreadsheets\SpreadsheetReadFailure;
use Exception;
use Generator;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use ZipArchive;

/**
 * SpreadsheetReaderInterface over openspout. Rows are streamed: reaching a limit stops at once.
 * Failures never keep openspout's exception, whose message could quote the file.
 */
final readonly class OpenSpoutSpreadsheetReader implements SpreadsheetReaderInterface
{
    /** An .xlsx may take at most this many times its size once uncompressed (a ZIP bomb guard). */
    public const int MAX_XLSX_EXPANSION = 100;

    private const string ZIP_SIGNATURE = "PK\x03\x04";

    private const string EMPTY_ZIP_SIGNATURE = "PK\x05\x06";

    /** Old binary Office files (.xls) and encrypted workbooks. */
    private const string OLE_SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    /** Control characters a CSV text never holds (tab and line breaks are allowed). */
    private const string BINARY_PATTERN = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';

    public function __construct(
        private CellText $cellText = new CellText,
    ) {}

    public function read(string $path, SpreadsheetLimitsDTO $limits = new SpreadsheetLimitsDTO): SpreadsheetDTO
    {
        $size = $this->sizeOf($path);

        if ($size === 0) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Empty);
        }

        if ($size > $limits->maxBytes) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::TooLarge, limit: $limits->maxBytes);
        }

        [$format, $reader] = $this->readerFor($path, $limits);

        try {
            $reader->open($path);
        } catch (Exception) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        try {
            return $this->collect($this->rows($reader), $format, $limits);
        } catch (SpreadsheetReadException $exception) {
            throw $exception;
        } catch (Exception) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        } finally {
            $reader->close();
        }
    }

    private function sizeOf(string $path): int
    {
        $size = is_file($path) && is_readable($path) ? filesize($path) : false;

        if ($size === false) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        return $size;
    }

    /**
     * The type comes from the content: an Office Open XML workbook or a text, never the extension.
     *
     * @return array{SpreadsheetFormat, CsvReader|XlsxReader}
     */
    private function readerFor(string $path, SpreadsheetLimitsDTO $limits): array
    {
        $signature = file_get_contents($path, length: strlen(self::OLE_SIGNATURE));

        if ($signature === false) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        if (str_starts_with($signature, self::ZIP_SIGNATURE) || str_starts_with($signature, self::EMPTY_ZIP_SIGNATURE)) {
            $this->assertWorkbook($path, $limits);

            $options = new XlsxOptions;
            $options->SHOULD_FORMAT_DATES = false;
            // Kept so the row numbers are the sheet's; blank rows are skipped when collecting.
            $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

            return [SpreadsheetFormat::Xlsx, new XlsxReader($options)];
        }

        if ($signature === self::OLE_SIGNATURE) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::UnsupportedType);
        }

        // A text needs all of it (bounded by maxBytes) to tell its encoding.
        $content = file_get_contents($path);

        if ($content === false) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        if (preg_match(self::BINARY_PATTERN, $content) === 1) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::UnsupportedType);
        }

        $dialect = CsvDialect::detect($content);
        unset($content);

        $options = new CsvOptions;
        $options->ENCODING = $dialect->encoding;
        $options->FIELD_DELIMITER = $dialect->delimiter;
        // Kept so the row numbers are the file's records; blank rows are skipped when collecting.
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return [SpreadsheetFormat::Csv, new CsvReader($options)];
    }

    private function assertWorkbook(string $path, SpreadsheetLimitsDTO $limits): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::UnsupportedType);
            }

            $expanded = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);

                if ($entry === false) {
                    throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
                }

                $expanded += $entry['size'];
            }

            if ($expanded > $limits->maxBytes * self::MAX_XLSX_EXPANSION) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::TooLarge, limit: $limits->maxBytes);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * The first sheet only, as trimmed texts keyed by column index.
     *
     * @return Generator<int, array<int, string>>
     */
    private function rows(CsvReader|XlsxReader $reader): Generator
    {
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $number => $row) {
                yield $number => array_map($this->cellText->of(...), $row->getCells());
            }

            return;
        }
    }

    /**
     * @param  iterable<int, array<int, string>>  $rows
     */
    private function collect(iterable $rows, SpreadsheetFormat $format, SpreadsheetLimitsDTO $limits): SpreadsheetDTO
    {
        $headers = null;
        $collected = [];

        foreach ($rows as $number => $cells) {
            $width = $this->widthOf($cells);

            if ($width === 0) {
                continue;
            }

            if ($width > $limits->maxColumns) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::TooManyColumns, limit: $limits->maxColumns);
            }

            if ($headers === null) {
                $headers = $this->headersOf($cells);

                continue;
            }

            $values = [];

            foreach ($headers as $index => $header) {
                $values[$header] = $cells[$index] ?? '';
            }

            if (implode('', $values) === '') {
                continue;
            }

            if (count($collected) >= $limits->maxRows) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::TooManyRows, limit: $limits->maxRows);
            }

            $collected[] = new SpreadsheetRowDTO($number, $values);
        }

        if ($headers === null || $collected === []) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Empty);
        }

        return new SpreadsheetDTO($format, array_values($headers), $collected);
    }

    /**
     * Columns up to the last one with text.
     *
     * @param  array<int, string>  $cells
     */
    private function widthOf(array $cells): int
    {
        $width = 0;

        foreach ($cells as $index => $text) {
            if ($text !== '') {
                $width = max($width, $index + 1);
            }
        }

        return $width;
    }

    /**
     * Header texts keyed by column index; columns without one are left out.
     *
     * @param  array<int, string>  $cells
     * @return array<int, string>
     */
    private function headersOf(array $cells): array
    {
        $headers = [];
        $seen = [];

        foreach ($cells as $index => $text) {
            if ($text === '') {
                continue;
            }

            $key = SpreadsheetHeader::normalise($text);

            if (isset($seen[$key])) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::DuplicateHeaders, column: $index + 1);
            }

            $seen[$key] = true;
            $headers[$index] = $text;
        }

        return $headers;
    }
}
