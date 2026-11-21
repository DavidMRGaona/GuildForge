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
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * SpreadsheetReaderInterface: .xlsx through openspout, .csv through CsvRecords. Rows are streamed:
 * reaching a limit stops at once. Failures never keep the underlying exception, whose message
 * could quote the file.
 */
final readonly class OpenSpoutSpreadsheetReader implements SpreadsheetReaderInterface
{
    /**
     * Cells built in one read, empty ones and empty rows included (at least this many, else
     * maxBytes): openspout fills in far-away empty cells and rows, which would otherwise cost time
     * without limit.
     */
    public const int MIN_CELL_BUDGET = 100_000;

    private const string ZIP_SIGNATURE = "PK\x03\x04";

    private const string EMPTY_ZIP_SIGNATURE = "PK\x05\x06";

    /** Old binary Office files (.xls) and encrypted workbooks. */
    private const string OLE_SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    /** Control characters a CSV text never holds (tab and line breaks are allowed). */
    private const string BINARY_PATTERN = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';

    public function __construct(
        private CellText $cellText = new CellText,
        private XlsxPreflight $preflight = new XlsxPreflight,
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

        $reader = null;

        try {
            $signature = file_get_contents($path, length: strlen(self::OLE_SIGNATURE));

            if ($signature === false) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
            }

            if (str_starts_with($signature, self::ZIP_SIGNATURE) || str_starts_with($signature, self::EMPTY_ZIP_SIGNATURE)) {
                $this->preflight->check($path, $limits);
                $reader = $this->xlsxReader();
                $reader->open($path);

                return $this->collect($this->xlsxRows($reader), SpreadsheetFormat::Xlsx, $limits);
            }

            if ($signature === self::OLE_SIGNATURE) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::UnsupportedType);
            }

            return $this->collect($this->csvRows($path, $limits), SpreadsheetFormat::Csv, $limits);
        } catch (SpreadsheetReadException $exception) {
            throw $exception;
        } catch (Exception) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        } finally {
            $reader?->close();
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

    private function xlsxReader(): XlsxReader
    {
        $options = new XlsxOptions;
        $options->SHOULD_FORMAT_DATES = false;
        // Kept so the row numbers are the sheet's; blank rows are skipped when collecting.
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return new XlsxReader($options);
    }

    /**
     * The first sheet only, as trimmed texts keyed by column index.
     *
     * @return Generator<int, array<int, string>>
     */
    private function xlsxRows(XlsxReader $reader): Generator
    {
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $number => $row) {
                yield $number => array_map($this->cellText->of(...), $row->getCells());
            }

            return;
        }
    }

    /**
     * Records of a CSV text, as trimmed texts. The text is read whole (bounded by maxBytes) to
     * tell its encoding, and split by CsvRecords rather than openspout, so the field cap is checked
     * by the very code that splits.
     *
     * @return Generator<int, list<string>>
     */
    private function csvRows(string $path, SpreadsheetLimitsDTO $limits): Generator
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        if (preg_match(self::BINARY_PATTERN, $content) === 1) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::UnsupportedType);
        }

        $dialect = CsvDialect::detect($content);
        $text = $dialect->toUtf8($content);
        unset($content);

        $records = CsvRecords::read($text, $dialect->delimiter, max(XlsxPreflight::MAX_SHEET_COLUMNS, $limits->maxColumns), $limits->maxColumns);

        foreach ($records as $number => $fields) {
            yield $number => array_map($this->cellText->ofText(...), $fields);
        }
    }

    /**
     * @param  iterable<int, array<int, string>>  $rows
     */
    private function collect(iterable $rows, SpreadsheetFormat $format, SpreadsheetLimitsDTO $limits): SpreadsheetDTO
    {
        $headers = null;
        $collected = [];
        $cellBudget = max(self::MIN_CELL_BUDGET, $limits->maxBytes);

        foreach ($rows as $number => $cells) {
            // An empty row openspout fills in still costs a turn of the loop.
            $cellBudget -= max(1, count($cells));

            if ($cellBudget < 0) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::TooLarge, limit: $limits->maxBytes);
            }

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
