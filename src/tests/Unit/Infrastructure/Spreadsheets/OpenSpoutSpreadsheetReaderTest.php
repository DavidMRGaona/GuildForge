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
        $this->assertStringStartsNotWith('=', $ana['Total'], 'A formula is read as its value, never as the formula');

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

    private function temporaryFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'gf-sheet-test-');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
