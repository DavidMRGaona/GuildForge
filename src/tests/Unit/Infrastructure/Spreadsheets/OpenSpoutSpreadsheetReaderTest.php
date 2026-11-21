<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Spreadsheets;

use App\Application\Spreadsheets\DTOs\SpreadsheetDTO;
use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use App\Application\Spreadsheets\Enums\SpreadsheetFormat;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Domain\Spreadsheets\SpreadsheetReadFailure;
use App\Infrastructure\Spreadsheets\OpenSpoutSpreadsheetReader;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class OpenSpoutSpreadsheetReaderTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../Fixtures/spreadsheets/';

    private OpenSpoutSpreadsheetReader $reader;

    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $temporaryFolders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new OpenSpoutSpreadsheetReader;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        foreach ($this->temporaryFolders as $folder) {
            @rmdir($folder);
        }

        parent::tearDown();
    }

    public function test_it_reads_the_first_sheet_of_an_xlsx_with_dates_and_plain_numbers(): void
    {
        $sheet = $this->reader->read(self::FIXTURES.'members.xlsx');

        $this->assertSame(SpreadsheetFormat::Xlsx, $sheet->format);
        $this->assertSame(['Nombre', 'Email', 'Fecha de alta', 'Cuota', 'Socio número', 'Activo', 'Total'], $sheet->headers);
        $this->assertSame([2, 4], $this->rowNumbers($sheet));

        $ana = $sheet->rows[0]->values;
        $this->assertSame('Ana Pérez', $ana['Nombre']);
        $this->assertSame('ana@example.org', $ana['Email']);
        $this->assertSame('2024-03-05', $ana['Fecha de alta']);
        $this->assertSame('1234.5', $ana['Cuota']);
        $this->assertSame('17', $ana['Socio número']);
        $this->assertSame('1', $ana['Activo']);
        $this->assertSame('2469', $ana['Total'], 'A formula is read as its stored result, never as the formula');

        $luis = $sheet->rows[1]->values;
        $this->assertSame('Luis Gómez', $luis['Nombre']);
        $this->assertSame('2025-11-20 18:30:00', $luis['Fecha de alta']);
        $this->assertSame('0.1', $luis['Cuota']);
        $this->assertSame('0042', $luis['Socio número'], 'Numbers stored as text keep their leading zeros');
        $this->assertSame('0', $luis['Activo']);
        $this->assertSame('', $luis['Total'], 'A missing cell is an empty text');
    }

    public function test_it_reads_a_utf8_csv_with_bom_and_commas(): void
    {
        $sheet = $this->reader->read(self::FIXTURES.'members-utf8-bom.csv');

        $this->assertSame(SpreadsheetFormat::Csv, $sheet->format);
        $this->assertSame(['Nombre', 'Email', 'Notas'], $sheet->headers);
        $this->assertSame([2, 5, 6], $this->rowNumbers($sheet), 'Blank records are skipped but still counted');
        $this->assertSame('Llega tarde, a veces', $sheet->rows[0]->value('Notas'));
        $this->assertSame('Íñigo Núñez', $sheet->rows[1]->value('Nombre'));
        $this->assertSame("Dos\nlíneas", $sheet->rows[1]->value('Notas'));
        $this->assertSame('Zoë', $sheet->rows[2]->value('Nombre'));
        $this->assertSame('Último', $sheet->rows[2]->value('Notas'));
    }

    public function test_it_reads_a_windows_1252_csv_with_semicolons(): void
    {
        $sheet = $this->reader->read(self::FIXTURES.'members-windows-1252.csv');

        $this->assertSame(['Nombre', 'Email', 'Cuota (€)'], $sheet->headers);
        $this->assertSame('José Martínez', $sheet->rows[0]->value('Nombre'));
        $this->assertSame('25,50', $sheet->rows[0]->value('Cuota (€)'), 'CSV text is kept as written');
        $this->assertSame('María Ñúñez', $sheet->rows[1]->value('Nombre'));
    }

    public function test_it_detects_tabs_as_separator(): void
    {
        $sheet = $this->reader->read(self::FIXTURES.'members-tabs.tsv');

        $this->assertSame(['Nombre', 'Email'], $sheet->headers);
        $this->assertSame(['Nombre' => 'Ana', 'Email' => 'ana@example.org'], $sheet->rows[0]->values);
    }

    public function test_a_separator_inside_a_quoted_header_does_not_count(): void
    {
        $sheet = $this->reader->read($this->temporaryFile("\"Apellidos, nombre\";Email\n\"Pérez, Ana\";ana@example.org\n"));

        $this->assertSame(['Apellidos, nombre', 'Email'], $sheet->headers);
        $this->assertSame('Pérez, Ana', $sheet->rows[0]->value('Apellidos, nombre'));
    }

    public function test_the_content_decides_the_type_not_the_extension(): void
    {
        $sheet = $this->reader->read(self::FIXTURES.'members-xlsx-named.csv');

        $this->assertSame(SpreadsheetFormat::Xlsx, $sheet->format);
        $this->assertSame('Ana Pérez', $sheet->rows[0]->value('Nombre'));
    }

    public function test_columns_without_header_are_left_out_and_missing_cells_are_empty(): void
    {
        $sheet = $this->reader->read($this->temporaryFile("Nombre;;Email;Notas\nAna;suelto;ana@example.org\n;solo aquí;;\n"));

        $this->assertSame(['Nombre', 'Email', 'Notas'], $sheet->headers);
        $this->assertCount(1, $sheet->rows, 'A row with text only under a column without header is empty');
        $this->assertSame(['Nombre' => 'Ana', 'Email' => 'ana@example.org', 'Notas' => ''], $sheet->rows[0]->values);
    }

    public function test_it_refuses_files_that_are_not_csv_or_xlsx(): void
    {
        $this->assertFailure(SpreadsheetReadFailure::UnsupportedType, self::FIXTURES.'document.xlsx');
        $this->assertFailure(SpreadsheetReadFailure::UnsupportedType, self::FIXTURES.'legacy.xls');
        $this->assertFailure(SpreadsheetReadFailure::UnsupportedType, $this->temporaryFile("\x89PNG\r\n\x1A\n\0\0\0\rIHDR\0\0\0\x01"));
        $this->assertFailure(SpreadsheetReadFailure::UnsupportedType, $this->temporaryFile("%PDF-1.7\n\x01\x02\x03 binary"));
    }

    public function test_it_reports_broken_or_missing_files_as_unreadable(): void
    {
        $this->assertFailure(SpreadsheetReadFailure::Unreadable, self::FIXTURES.'broken.xlsx');
        $this->assertFailure(SpreadsheetReadFailure::Unreadable, self::FIXTURES.'does-not-exist.csv');
        $this->assertFailure(SpreadsheetReadFailure::Unreadable, self::FIXTURES);
    }

    public function test_it_reports_files_without_data_rows_as_empty(): void
    {
        $this->assertFailure(SpreadsheetReadFailure::Empty, self::FIXTURES.'blank.csv');
        $this->assertFailure(SpreadsheetReadFailure::Empty, self::FIXTURES.'header-only.csv');
        $this->assertFailure(SpreadsheetReadFailure::Empty, $this->temporaryFile(''));
    }

    public function test_it_refuses_repeated_headers_ignoring_case_and_accents(): void
    {
        $exception = $this->assertFailure(SpreadsheetReadFailure::DuplicateHeaders, self::FIXTURES.'duplicate-headers.xlsx');

        $this->assertSame(3, $exception->column);
        $this->assertStringNotContainsStringIgnoringCase('tel', $exception->getMessage(), 'The message never carries the file content');
    }

    public function test_it_refuses_files_over_the_byte_limit(): void
    {
        $exception = $this->assertFailure(SpreadsheetReadFailure::TooLarge, self::FIXTURES.'members-utf8-bom.csv', new SpreadsheetLimitsDTO(maxBytes: 100));

        $this->assertSame(100, $exception->limit);
    }

    public function test_it_refuses_an_xlsx_that_expands_far_beyond_the_byte_limit(): void
    {
        $path = $this->temporaryFile('');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('xl/workbook.xml', '<workbook/>');
        $zip->addFromString('xl/worksheets/sheet1.xml', str_repeat(' ', 4 * 1024 * 1024));
        $zip->close();

        $this->assertLessThan(20_000, (int) filesize($path));
        $this->assertFailure(SpreadsheetReadFailure::TooLarge, $path, new SpreadsheetLimitsDTO(maxBytes: 20_000));
    }

    public function test_it_counts_the_bytes_really_uncompressed_not_the_declared_ones(): void
    {
        $path = $this->workbook(['xl/worksheets/sheet1.xml' => $this->sheetXml(str_repeat(' ', 4 * 1024 * 1024))]);
        $this->understateUncompressedSizes($path);

        $this->assertFailure(SpreadsheetReadFailure::TooLarge, $path, new SpreadsheetLimitsDTO(maxBytes: 20_000));
    }

    public function test_a_csv_record_with_more_fields_than_any_spreadsheet_is_refused_before_reading(): void
    {
        $this->assertFailure(SpreadsheetReadFailure::TooManyColumns, $this->temporaryFile('Nombre'.str_repeat(';', 20_000)."\nAna\n"));
    }

    public function test_an_xlsx_cell_beyond_the_last_spreadsheet_column_is_refused_before_reading(): void
    {
        $this->assertFailure(SpreadsheetReadFailure::TooManyColumns, $this->workbook([
            'xl/worksheets/sheet1.xml' => $this->sheetXml('<row r="1"><c r="A1" t="inlineStr"><is><t>Nombre</t></is></c><c r="ZZZZ1"/></row>'),
        ]));
    }

    public function test_an_xlsx_row_with_more_cells_than_any_spreadsheet_is_refused_before_reading(): void
    {
        $this->assertFailure(SpreadsheetReadFailure::TooManyColumns, $this->workbook([
            'xl/worksheets/sheet1.xml' => $this->sheetXml('<row r="1">'.str_repeat('<c/>', 16_385).'</row>'),
        ]));
    }

    public function test_it_stops_when_far_away_empty_cells_add_up_beyond_the_cell_budget(): void
    {
        $rows = '<row r="1"><c r="A1" t="inlineStr"><is><t>Nombre</t></is></c></row>';

        for ($row = 2; $row <= 400; $row++) {
            $rows .= '<row r="'.$row.'"><c r="XFD'.$row.'"/></row>';
        }

        $this->assertBoundedFailure(
            SpreadsheetReadFailure::TooManyColumns,
            $this->workbook(['xl/worksheets/sheet1.xml' => $this->sheetXml($rows)]),
            new SpreadsheetLimitsDTO(maxRows: 10),
        );
    }

    public function test_a_tag_too_long_for_a_regular_expression_hides_no_cell(): void
    {
        $block = '<c'.str_repeat(' ', 12_000).'/>'.str_repeat('<c/>', 8_000);

        $this->assertFailure(SpreadsheetReadFailure::TooManyColumns, $this->workbook([
            'xl/worksheets/sheet1.xml' => $this->sheetXml('<row r="1"><c r="A1" t="inlineStr"><is><t>Nombre</t></is></c>'.str_repeat($block, 3).'</row>'),
        ]));
    }

    public function test_cell_references_written_as_character_references_are_read_as_openspout_reads_them(): void
    {
        $this->assertFailure(SpreadsheetReadFailure::TooManyColumns, $this->workbook([
            'xl/worksheets/sheet1.xml' => $this->sheetXml('<row r="1"><c r="A1" t="inlineStr"><is><t>Nombre</t></is></c><c r="&#90;&#90;&#90;&#90;1"/></row>'),
        ]));
    }

    public function test_a_broken_workbook_that_makes_openspout_fail_with_an_error_is_unreadable(): void
    {
        // Cells pointing to shared strings in a workbook without them make openspout hit an uninitialised property.
        $this->assertFailure(SpreadsheetReadFailure::Unreadable, $this->workbook([], remove: ['xl/sharedStrings.xml']));
    }

    public function test_a_formula_without_a_stored_result_arrives_as_zero(): void
    {
        $sheet = $this->reader->read($this->workbook([
            'xl/worksheets/sheet1.xml' => $this->sheetXml(
                '<row r="1"><c r="A1" t="inlineStr"><is><t>Nombre</t></is></c><c r="B1" t="inlineStr"><is><t>Total</t></is></c></row>'
                .'<row r="2"><c r="A2" t="inlineStr"><is><t>Ana</t></is></c><c r="B2"><f>1+1</f></c></row>',
            ),
        ]));

        $this->assertSame('0', $sheet->rows[0]->value('Total'));
    }

    public function test_numeric_headers_are_found_by_their_text(): void
    {
        $sheet = $this->reader->read($this->temporaryFile('2023;2024
1;2
'));

        $this->assertSame(['2023', '2024'], $sheet->headers);
        $this->assertSame('2', $sheet->rows[0]->value('2024'));
        $this->assertNull($sheet->rows[0]->value('2025'));
    }

    public function test_no_temporary_file_outlives_a_read_even_a_failed_one(): void
    {
        $root = $this->temporaryFolder();
        $reader = new OpenSpoutSpreadsheetReader(temporaryRoot: $root);

        $reader->read(self::FIXTURES.'members.xlsx');

        try {
            $reader->read($this->workbook(['xl/sharedStrings.xml' => '<sst><si><t>texto</si>']));
        } catch (SpreadsheetReadException) {
        }

        $this->assertSame([], array_values(array_diff((array) scandir($root), ['.', '..'])));
    }

    public function test_a_quote_inside_an_unquoted_csv_field_is_text_and_hides_no_separator(): void
    {
        $sheet = $this->reader->read($this->temporaryFile("Nombre;Nota\nAna;dijo \"hola\"\n\"Luis\";\"he said \"\"hi\"\"\"\n"));

        $this->assertSame('dijo "hola"', $sheet->rows[0]->value('Nota'));
        $this->assertSame('he said "hi"', $sheet->rows[1]->value('Nota'));

        $this->assertFailure(SpreadsheetReadFailure::TooManyColumns, $this->temporaryFile('Nombre;a"b'.str_repeat(';', 20_000)."\nAna\n"));
    }

    public function test_every_part_of_the_workbook_is_checked_not_only_the_usual_sheet_names(): void
    {
        $rels = $this->entryOf(self::FIXTURES.'members.xlsx', 'xl/_rels/workbook.xml.rels');

        $this->assertFailure(SpreadsheetReadFailure::TooManyColumns, $this->workbook([
            'xl/_rels/workbook.xml.rels' => str_replace('worksheets/sheet1.xml', 'datos/hoja.xml', $rels),
            'xl/datos/hoja.xml' => $this->sheetXml('<row r="1"><c r="A1" t="inlineStr"><is><t>Nombre</t></is></c><c r="ZZZZ1"/></row>'),
        ]));
    }

    public function test_a_row_number_beyond_the_last_spreadsheet_row_is_refused_before_reading(): void
    {
        $this->assertFailure(SpreadsheetReadFailure::TooManyRows, $this->workbook([
            'xl/worksheets/sheet1.xml' => $this->sheetXml('<row r="1"><c r="A1" t="inlineStr"><is><t>Nombre</t></is></c></row><row r="2147483647"/>'),
        ]));
    }

    public function test_empty_rows_filled_in_by_openspout_count_against_the_budget(): void
    {
        $this->assertBoundedFailure(
            SpreadsheetReadFailure::TooManyRows,
            $this->workbook(['xl/worksheets/sheet1.xml' => $this->sheetXml(
                '<row r="1"><c r="A1" t="inlineStr"><is><t>Nombre</t></is></c></row>'
                .'<row r="1048576"><c r="A1048576" t="inlineStr"><is><t>Ana</t></is></c></row>',
            )]),
        );
    }

    public function test_blank_csv_lines_count_against_the_budget(): void
    {
        $this->assertBoundedFailure(
            SpreadsheetReadFailure::TooManyRows,
            $this->temporaryFile('Nombre;Email
'.str_repeat("\n", 5 * 1024 * 1024 - 20).'Ana;a'),
        );
    }

    public function test_excel_csv_variants_are_read(): void
    {
        $withSeparatorLine = $this->reader->read($this->temporaryFile("sep=,\r\nNombre,Notas\r\nAna,\"a; b\"\r\n"));
        $this->assertSame(['Nombre', 'Notas'], $withSeparatorLine->headers);
        $this->assertSame(2, $withSeparatorLine->rows[0]->number, 'The sep= line is not a row, as in Excel');

        $carriageReturns = $this->reader->read($this->temporaryFile("Nombre;Email\rAna;ana@example.org\rLuis;luis@example.org\r"));
        $this->assertSame([2, 3], array_map(static fn ($row): int => $row->number, $carriageReturns->rows));

        $endOfFileMark = $this->reader->read($this->temporaryFile("Nombre;Email\r\nAna;ana@example.org\r\n\x1A"));
        $this->assertCount(1, $endOfFileMark->rows);

        $multilineHeader = $this->reader->read($this->temporaryFile("\"Nombre\ncompleto\";Email;Notas\nAna;ana@example.org;x\n"));
        $this->assertSame(["Nombre\ncompleto", 'Email', 'Notas'], $multilineHeader->headers);

        $commasInHeader = $this->reader->read($this->temporaryFile("Nombre;Dirección (calle, número, piso)\nAna;Mayor, 3, 2º\n"));
        $this->assertSame(['Nombre', 'Dirección (calle, número, piso)'], $commasInHeader->headers);
    }

    public function test_it_stops_at_the_row_limit(): void
    {
        $exception = $this->assertFailure(SpreadsheetReadFailure::TooManyRows, self::FIXTURES.'members-utf8-bom.csv', new SpreadsheetLimitsDTO(maxRows: 2));
        $this->assertSame(2, $exception->limit);

        $sheet = $this->reader->read(self::FIXTURES.'members-utf8-bom.csv', new SpreadsheetLimitsDTO(maxRows: 3));
        $this->assertCount(3, $sheet->rows);
    }

    public function test_it_refuses_headers_or_rows_wider_than_the_column_limit(): void
    {
        $exception = $this->assertFailure(SpreadsheetReadFailure::TooManyColumns, self::FIXTURES.'members.xlsx', new SpreadsheetLimitsDTO(maxColumns: 6));
        $this->assertSame(6, $exception->limit);

        $this->assertFailure(SpreadsheetReadFailure::TooManyColumns, $this->temporaryFile("a;b\n1;2;3;4\n"), new SpreadsheetLimitsDTO(maxColumns: 3));
    }

    public function test_failures_never_keep_the_underlying_exception(): void
    {
        $exception = $this->assertFailure(SpreadsheetReadFailure::Unreadable, self::FIXTURES.'broken.xlsx');

        $this->assertNull($exception->getPrevious());
    }

    /**
     * The failure must come early: these files would otherwise cost minutes.
     */
    private function assertBoundedFailure(SpreadsheetReadFailure $reason, string $path, ?SpreadsheetLimitsDTO $limits = null): void
    {
        $started = microtime(true);
        $this->assertFailure($reason, $path, $limits);
        $this->assertLessThan(10.0, microtime(true) - $started, 'Reading must stop early');
    }

    private function assertFailure(SpreadsheetReadFailure $reason, string $path, ?SpreadsheetLimitsDTO $limits = null): SpreadsheetReadException
    {
        try {
            $this->reader->read($path, $limits ?? new SpreadsheetLimitsDTO);
        } catch (SpreadsheetReadException $exception) {
            $this->assertSame($reason, $exception->reason, 'Reading '.basename($path));

            return $exception;
        }

        $this->fail('Expected '.$reason->value.' reading '.basename($path));
    }

    /**
     * @return list<int>
     */
    private function rowNumbers(SpreadsheetDTO $sheet): array
    {
        return array_map(static fn ($row): int => $row->number, $sheet->rows);
    }

    /**
     * A workbook built over a real one (members.xlsx), with the given entries replaced.
     *
     * @param  array<string, string>  $entries
     * @param  list<string>  $remove
     */
    private function workbook(array $entries, array $remove = []): string
    {
        $path = $this->temporaryFile((string) file_get_contents(self::FIXTURES.'members.xlsx'));
        $zip = new ZipArchive;
        $zip->open($path);

        foreach ($remove as $name) {
            $zip->deleteName($name);
        }

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    private function entryOf(string $path, string $name): string
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $contents = (string) $zip->getFromName($name);
        $zip->close();

        return $contents;
    }

    private function sheetXml(string $rows): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .$rows.'</sheetData></worksheet>';
    }

    /**
     * Rewrites every uncompressed size in the archive's headers to 1 byte, as a crafted ZIP bomb would.
     */
    private function understateUncompressedSizes(string $path): void
    {
        $bytes = (string) file_get_contents($path);

        foreach (["PK\x03\x04" => 22, "PK\x01\x02" => 24] as $signature => $offset) {
            $position = 0;

            while (($position = strpos($bytes, $signature, $position)) !== false) {
                $bytes = substr_replace($bytes, pack('V', 1), $position + $offset, 4);
                $position += 4;
            }
        }

        file_put_contents($path, $bytes);
    }

    private function temporaryFolder(): string
    {
        $path = $this->temporaryFile('');
        unlink($path);
        mkdir($path);
        $this->temporaryFolders[] = $path;

        return $path;
    }

    private function temporaryFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'gf-sheet-test-');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
