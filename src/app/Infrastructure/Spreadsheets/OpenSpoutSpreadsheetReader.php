<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

use App\Application\Spreadsheets\DTOs\SpreadsheetDTO;
use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use App\Application\Spreadsheets\DTOs\SpreadsheetRowDTO;
use App\Application\Spreadsheets\Enums\SpreadsheetFormat;
use App\Application\Spreadsheets\Services\SpreadsheetReaderInterface;
use App\Application\Spreadsheets\SpreadsheetHeader;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Domain\Spreadsheets\SpreadsheetReadFailure;
use Generator;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * SpreadsheetReaderInterface: .xlsx through openspout, .csv through CsvRecords. Rows are streamed:
 * reaching a limit stops at once. Failures never keep the underlying exception, whose message
 * could quote the file.
 */
final readonly class OpenSpoutSpreadsheetReader implements SpreadsheetReaderInterface
{
    /**
     * Cells built in one read, empty ones included and at least one per row, empty rows too, as a
     * multiple of what the limits allow ((maxRows + 1) × maxColumns): openspout fills in far-away
     * empty cells and rows, which would otherwise cost time without limit.
     */
    public const int CELL_BUDGET_FACTOR = 4;

    /** The budget never goes below this, so small limits still leave room for blank rows. */
    public const int MIN_CELL_BUDGET = 10_000;

    private const string ZIP_SIGNATURE = "PK\x03\x04";

    private const string EMPTY_ZIP_SIGNATURE = "PK\x05\x06";

    /** Old binary Office files (.xls) and encrypted workbooks. */
    private const string OLE_SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    /** Control characters a CSV text never holds (tab and line breaks are allowed). */
    private const string BINARY_PATTERN = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';

    /** The end-of-file mark old programs leave after the last line. */
    private const string END_OF_FILE_MARK = "\x1A";

    public function __construct(
        private CellText $cellText = new CellText,
        private XlsxPreflight $preflight = new XlsxPreflight,
        private ?string $temporaryRoot = null,
    ) {}

    /**
     * Cells one read may build under these limits.
     */
    public static function cellBudget(SpreadsheetLimitsDTO $limits): int
    {
        return max(self::MIN_CELL_BUDGET, ($limits->maxRows + 1) * $limits->maxColumns * self::CELL_BUDGET_FACTOR);
    }

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
        $temporaryFolder = null;

        try {
            $signature = file_get_contents($path, length: strlen(self::OLE_SIGNATURE));

            if ($signature === false) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
            }

            if (str_starts_with($signature, self::ZIP_SIGNATURE) || str_starts_with($signature, self::EMPTY_ZIP_SIGNATURE)) {
                $this->preflight->check($path, $limits);
                $temporaryFolder = TemporaryFolder::create($this->temporaryRoot);
                $reader = $this->xlsxReader($temporaryFolder->path);
                $reader->open($path);

                return $this->collect($this->xlsxRows($reader), SpreadsheetFormat::Xlsx, $limits);
            }

            if ($signature === self::OLE_SIGNATURE) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::UnsupportedType);
            }

            return $this->collect($this->csvRows($path, $limits), SpreadsheetFormat::Csv, $limits);
        } catch (SpreadsheetReadException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Also errors: openspout may hit an uninitialised property on a malformed workbook.
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        } finally {
            try {
                $reader?->close();
            } catch (Throwable) {
                // Closing a reader that never opened may fail; the folder goes all the same.
            }

            $temporaryFolder?->delete();
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

    private function xlsxReader(string $temporaryFolder): XlsxReader
    {
        $options = new XlsxOptions;
        $options->SHOULD_FORMAT_DATES = false;
        // Kept so the row numbers are the sheet's; blank rows are skipped when collecting.
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        // Shared strings may be cached on disk: in a folder of this read alone, deleted afterwards.
        $options->setTempFolder($temporaryFolder);

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

        if (str_ends_with($content, self::END_OF_FILE_MARK)) {
            $content = substr($content, 0, -1);
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
        $cellBudget = self::cellBudget($limits);

        foreach ($rows as $number => $cells) {
            // An empty row openspout fills in, or a blank CSV line, still costs a turn of the loop.
            $cellBudget -= max(1, count($cells));

            if ($cellBudget < 0) {
                // Wide rows of empty cells spend it on columns; blank rows, on rows.
                throw count($cells) > $limits->maxColumns
                    ? new SpreadsheetReadException(SpreadsheetReadFailure::TooManyColumns, limit: $limits->maxColumns)
                    : new SpreadsheetReadException(SpreadsheetReadFailure::TooManyRows, limit: $limits->maxRows);
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
