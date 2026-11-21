<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Domain\Spreadsheets\SpreadsheetReadFailure;
use Throwable;
use XMLReader;
use ZipArchive;

/**
 * Checks an .xlsx before openspout opens it, since openspout builds a whole row in memory, filling
 * the gaps up to its last cell, and every empty row up to the next row number:
 *
 * - every entry is inflated once and its real bytes counted (the sizes an archive declares can lie);
 * - the entries openspout parses (every sheet the workbook points to, whatever its name, those in
 *   xl/worksheets/ and the shared strings) are read with XMLReader as openspout reads them
 *   (elements matched by local name, whatever their namespace): no row may reach past the last
 *   spreadsheet column, by a cell reference or by its number of cells, no row number may pass the
 *   last spreadsheet row, and no entry may hold more elements than the limits can need. Other
 *   entries only count for the inflated bytes.
 *
 * Anything that cannot be scanned is unreadable: it fails closed.
 */
final class XlsxPreflight
{
    /** An .xlsx may take at most this many times maxBytes once uncompressed (a ZIP bomb guard). */
    public const int MAX_EXPANSION = 30;

    /** Columns of Excel and LibreOffice (A to XFD). */
    public const int MAX_SHEET_COLUMNS = 16384;

    /** Rows of Excel and LibreOffice. */
    public const int MAX_SHEET_ROWS = 1048576;

    /** Elements an entry read may hold, per cell the reader's budget allows. */
    public const int ELEMENTS_PER_CELL = 16;

    private const int CHUNK_BYTES = 65536;

    private const string WORKBOOK = 'xl/workbook.xml';

    private const string WORKBOOK_RELATIONS = 'xl/_rels/workbook.xml.rels';

    private const string WORKSHEETS_FOLDER = 'xl/worksheets/';

    private const string SHARED_STRINGS = 'xl/sharedStrings.xml';

    private const string SHARED_STRINGS_RELATION = '/sharedStrings';

    private const string RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const string CELL_REFERENCE = '/^([A-Za-z]{1,8})(\d{1,9})$/';

    public function check(string $path, SpreadsheetLimitsDTO $limits): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName(self::WORKBOOK) === false) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::UnsupportedType);
            }

            $names = $this->inflateAll($zip, $limits);
        } finally {
            $zip->close();
        }

        $maxColumns = max(self::MAX_SHEET_COLUMNS, $limits->maxColumns);
        $maxElements = OpenSpoutSpreadsheetReader::cellBudget($limits) * self::ELEMENTS_PER_CELL;

        foreach ($this->entriesToScan($path, $names) as $name) {
            $this->scan($this->uri($path, $name), $limits, $maxColumns, $maxElements);
        }
    }

    /**
     * @return list<string> Every entry name
     */
    private function inflateAll(ZipArchive $zip, SpreadsheetLimitsDTO $limits): array
    {
        $budget = $limits->maxBytes * self::MAX_EXPANSION;
        $names = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $stream = $name === false ? false : $zip->getStream($name);

            if ($name === false || $stream === false) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
            }

            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, self::CHUNK_BYTES);

                    if ($chunk === false) {
                        throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
                    }

                    $budget -= strlen($chunk);

                    if ($budget < 0) {
                        throw new SpreadsheetReadException(SpreadsheetReadFailure::TooLarge, limit: $limits->maxBytes);
                    }
                }
            } finally {
                fclose($stream);
            }

            $names[] = $name;
        }

        return $names;
    }

    /**
     * What openspout parses: the targets of the workbook's sheets and shared strings, which may have
     * any name, everything in xl/worksheets/ and xl/sharedStrings.xml.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function entriesToScan(string $path, array $names): array
    {
        $scan = array_filter(
            $names,
            static fn (string $name): bool => str_starts_with($name, self::WORKSHEETS_FOLDER) || $name === self::SHARED_STRINGS,
        );

        foreach ($this->relationTargets($path, $names) as $target) {
            $scan[] = $target;
        }

        return array_values(array_unique($scan));
    }

    /**
     * The entries the workbook's <sheet r:id> and its shared strings relation point to, resolved
     * as paths inside the archive. The id is read both by qualified name and by namespace.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function relationTargets(string $path, array $names): array
    {
        if (! in_array(self::WORKBOOK_RELATIONS, $names, true)) {
            return [];
        }

        $ids = [];

        $this->each($this->uri($path, self::WORKBOOK), function (XMLReader $xml) use (&$ids): void {
            if ($xml->localName === 'sheet') {
                $ids[] = (string) $xml->getAttribute('r:id');
                $ids[] = (string) $xml->getAttributeNs('id', self::RELATIONSHIPS_NAMESPACE);
            }
        });

        $targets = [];

        $this->each($this->uri($path, self::WORKBOOK_RELATIONS), function (XMLReader $xml) use ($ids, $names, &$targets): void {
            if ($xml->localName !== 'Relationship') {
                return;
            }

            $isSheet = in_array((string) $xml->getAttribute('Id'), $ids, true);
            $isSharedStrings = str_ends_with((string) $xml->getAttribute('Type'), self::SHARED_STRINGS_RELATION);

            if (! $isSheet && ! $isSharedStrings) {
                return;
            }

            // External targets (URLs) are never opened.
            if ($xml->getAttribute('TargetMode') === 'External') {
                return;
            }

            $target = $this->resolve((string) $xml->getAttribute('Target'));

            if ($target !== null && in_array($target, $names, true)) {
                $targets[] = $target;
            }
        });

        return $targets;
    }

    /**
     * A relation target as the entry name openspout opens, taken literally as it does: under xl/
     * unless it already starts with /xl/, without resolving «.», «..» or doubled slashes. A name
     * with any of them (no spreadsheet program writes one) could make this check and openspout
     * read different entries, so it is unreadable.
     */
    private function resolve(string $target): ?string
    {
        if ($target === '') {
            return null;
        }

        $name = str_starts_with($target, '/xl/') ? substr($target, 1) : 'xl/'.$target;
        $segments = explode('/', $name);

        if (in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        return $name;
    }

    private function scan(string $uri, SpreadsheetLimitsDTO $limits, int $maxColumns, int $maxElements): void
    {
        $rowWidth = 0;
        $elements = 0;

        $this->each($uri, function (XMLReader $xml) use (&$rowWidth, &$elements, $limits, $maxColumns, $maxElements): void {
            if (++$elements > $maxElements) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::TooLarge, limit: $limits->maxBytes);
            }

            if ($xml->localName === 'row') {
                $rowWidth = 0;
                $number = $xml->getAttribute('r');

                if ($number !== null) {
                    $this->assertRowNumber($number, $limits);
                }

                return;
            }

            if ($xml->localName !== 'c') {
                return;
            }

            $reference = $xml->getAttribute('r');

            if ($reference === null) {
                // A cell without a reference takes the column after the previous one.
                $rowWidth++;
            } else {
                if (preg_match(self::CELL_REFERENCE, $reference, $parts) !== 1) {
                    throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
                }

                $this->assertRowNumber($parts[2], $limits);
                $rowWidth = max($rowWidth, $this->columnIndex($parts[1], $maxColumns));
            }

            if ($rowWidth > $maxColumns) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::TooManyColumns, limit: $limits->maxColumns);
            }
        });
    }

    private function assertRowNumber(string $number, SpreadsheetLimitsDTO $limits): void
    {
        if (! ctype_digit($number)) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        }

        if (strlen($number) > 7 || (int) $number > self::MAX_SHEET_ROWS) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::TooManyRows, limit: $limits->maxRows);
        }
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

    /**
     * Calls $visit on every start tag of an XML entry. Any XML error ends as unreadable.
     *
     * @param  callable(XMLReader): void  $visit
     */
    private function each(string $uri, callable $visit): void
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $xml = new XMLReader;

        try {
            if (! $xml->open($uri, null, LIBXML_NONET)) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
            }

            while ($xml->read()) {
                if ($xml->nodeType === XMLReader::ELEMENT) {
                    $visit($xml);
                }
            }

            if (libxml_get_errors() !== []) {
                throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
            }
        } catch (SpreadsheetReadException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new SpreadsheetReadException(SpreadsheetReadFailure::Unreadable);
        } finally {
            $xml->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function uri(string $path, string $name): string
    {
        return 'zip://'.$path.'#'.$name;
    }
}
