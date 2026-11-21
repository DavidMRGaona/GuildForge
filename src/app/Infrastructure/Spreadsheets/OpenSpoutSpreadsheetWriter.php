<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use App\Application\Spreadsheets\Services\SpreadsheetWriterInterface;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetWriteException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

/**
 * SpreadsheetWriterInterface over openspout. Texts are always string cells: openspout's
 * Cell::fromValue() would turn a text starting with «=» into a formula.
 */
final readonly class OpenSpoutSpreadsheetWriter implements SpreadsheetWriterInterface
{
    /**
     * @param  string|null  $temporaryRoot  Where each call's temporary folder goes; the system's by default
     */
    public function __construct(
        private ?string $temporaryRoot = null,
    ) {}

    private const int MIN_COLUMN_WIDTH = 10;

    private const int MAX_COLUMN_WIDTH = 60;

    /** Room for the bold header and the filter arrow some programs add. */
    private const int COLUMN_PADDING = 3;

    public function write(SpreadsheetTemplateDTO $template): string
    {
        $folder = null;

        try {
            // openspout writes the workbook's parts in its temporary folder before zipping them.
            $folder = TemporaryFolder::create($this->temporaryRoot);
            $path = $folder->path.DIRECTORY_SEPARATOR.'template.xlsx';
            $this->writeTo($path, $folder->path, $template);
            $bytes = file_get_contents($path);
        } catch (Throwable) {
            throw SpreadsheetWriteException::failed();
        } finally {
            $folder?->delete();
        }

        if ($bytes === false || $bytes === '') {
            throw SpreadsheetWriteException::failed();
        }

        return $bytes;
    }

    private function writeTo(string $path, string $temporaryFolder, SpreadsheetTemplateDTO $template): void
    {
        $options = new Options;
        $options->setTempFolder($temporaryFolder);
        $writer = new Writer($options);
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($template->sheetName);
        // Rows above the second one stay in view while scrolling.
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));

        // Widths must be set before the first row is written.
        foreach ($this->columnWidths($template) as $column => $width) {
            $sheet->setColumnWidth($width, $column);
        }

        $writer->addRow(new Row(
            array_map(static fn (string $header): Cell => new StringCell($header, null), $template->headers),
            (new Style)->setFontBold(),
        ));

        foreach ($template->rows as $values) {
            $writer->addRow(new Row(array_map($this->cell(...), $values)));
        }

        $writer->close();
    }

    private function cell(string|int|float|null $value): Cell
    {
        return match (true) {
            $value === null => new EmptyCell(null, null),
            is_string($value) => new StringCell($value, null),
            default => new NumericCell($value, null),
        };
    }

    /**
     * @return array<int<1, max>, float> Width in characters keyed by 1-based column
     */
    private function columnWidths(SpreadsheetTemplateDTO $template): array
    {
        $widths = [];

        foreach ($template->headers as $index => $header) {
            $longest = mb_strlen($header);

            foreach ($template->rows as $values) {
                $longest = max($longest, mb_strlen((string) ($values[$index] ?? '')));
            }

            $widths[$index + 1] = (float) max(self::MIN_COLUMN_WIDTH, min(self::MAX_COLUMN_WIDTH, $longest + self::COLUMN_PADDING));
        }

        return $widths;
    }
}
