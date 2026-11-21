<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use App\Application\Spreadsheets\SpreadsheetWriterInterface;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetWriteException;
use Exception;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * SpreadsheetWriterInterface over openspout. Texts are always string cells: openspout's
 * Cell::fromValue() would turn a text starting with «=» into a formula.
 */
final class OpenSpoutSpreadsheetWriter implements SpreadsheetWriterInterface
{
    private const int MIN_COLUMN_WIDTH = 10;

    private const int MAX_COLUMN_WIDTH = 60;

    /** Room for the bold header and the filter arrow some programs add. */
    private const int COLUMN_PADDING = 3;

    public function write(SpreadsheetTemplateDTO $template): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gf-sheet-');

        if ($path === false) {
            throw SpreadsheetWriteException::failed();
        }

        try {
            $this->writeTo($path, $template);
            $bytes = file_get_contents($path);
        } catch (Exception) {
            throw SpreadsheetWriteException::failed();
        } finally {
            @unlink($path);
        }

        if ($bytes === false || $bytes === '') {
            throw SpreadsheetWriteException::failed();
        }

        return $bytes;
    }

    private function writeTo(string $path, SpreadsheetTemplateDTO $template): void
    {
        $writer = new Writer;
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
