<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Spreadsheets;

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
}
