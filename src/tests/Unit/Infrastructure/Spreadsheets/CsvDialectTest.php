<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Spreadsheets;

use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Infrastructure\Spreadsheets\CsvDialect;
use PHPUnit\Framework\TestCase;

final class CsvDialectTest extends TestCase
{
    public function test_it_tells_the_encoding_and_the_separator(): void
    {
        $this->assertEquals(new CsvDialect('UTF-8', ','), CsvDialect::detect("\u{FEFF}a,b\n1,2"));
        $this->assertEquals(new CsvDialect('Windows-1252', ';'), CsvDialect::detect("Cuota (\x80);Nombre\n"));
        $this->assertEquals(new CsvDialect('UTF-8', "\t"), CsvDialect::detect("a\tb\n"));
        $this->assertEquals(new CsvDialect('UTF-8', ';'), CsvDialect::detect("\n;;\na;b,c\n"), 'Blank lines are not the header; `;` wins a tie');
        $this->assertEquals(new CsvDialect('UTF-8', ','), CsvDialect::detect("Nombre\n"));
    }

    public function test_the_separator_comes_from_whole_records_not_from_a_line(): void
    {
        $this->assertSame(';', CsvDialect::detect("\"Nombre\ncompleto\";Email;Notas\nAna;ana@example.org;x\n")->delimiter);
        $this->assertSame(';', CsvDialect::detect("Nombre;Dirección (calle, número, piso)\nAna;Mayor, 3, 2º\n")->delimiter, 'Equal counts in every record, `;` first');
        $this->assertSame(',', CsvDialect::detect("Nombre,Notas\nAna,a;b\nLuis,c\n")->delimiter);
        $this->assertSame(';', CsvDialect::detect("Nombre;Email\rAna;ana@example.org\r")->delimiter, 'Lines ended by CR alone');
    }

    public function test_excel_s_separator_line_names_the_separator_and_is_left_out(): void
    {
        $dialect = CsvDialect::detect("\u{FEFF}sep=|\r\nNombre|Email\r\n");

        $this->assertEquals(new CsvDialect('UTF-8', '|', hasSeparatorLine: true), $dialect);
        $this->assertSame("Nombre|Email\r\n", $dialect->toUtf8("\u{FEFF}sep=|\r\nNombre|Email\r\n"));
    }

    public function test_windows_1252_text_is_converted_to_utf8(): void
    {
        $dialect = CsvDialect::detect("Jos\xE9;Cuota (\x80)\n");

        $this->assertSame('Windows-1252', $dialect->encoding);
        $this->assertSame("José;Cuota (€)\n", $dialect->toUtf8("Jos\xE9;Cuota (\x80)\n"));
    }

    public function test_bytes_windows_1252_does_not_define_make_the_text_unreadable(): void
    {
        $content = "Jos\x81;Cuota\n";
        $dialect = CsvDialect::detect($content);

        $this->expectException(SpreadsheetReadException::class);

        $dialect->toUtf8($content);
    }
}
