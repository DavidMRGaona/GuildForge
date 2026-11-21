<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Spreadsheets;

use App\Application\Spreadsheets\SpreadsheetHeader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpreadsheetHeaderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function equivalentHeaders(): iterable
    {
        yield 'case' => ['Email', 'EMAIL'];
        yield 'accents' => ['Teléfono', 'telefono'];
        yield 'eñe' => ['Año de alta', 'ano de alta'];
        yield 'spaces' => ['  Fecha   de  alta ', 'fecha de alta'];
        yield 'non-breaking space' => ["Fecha\u{A0}de alta", 'Fecha de alta'];
    }

    #[DataProvider('equivalentHeaders')]
    public function test_headers_match_ignoring_case_accents_and_spacing(string $written, string $expected): void
    {
        $this->assertSame(SpreadsheetHeader::normalise($expected), SpreadsheetHeader::normalise($written));
    }

    public function test_different_headers_stay_different(): void
    {
        $this->assertNotSame(SpreadsheetHeader::normalise('Nombre'), SpreadsheetHeader::normalise('Apellidos'));
    }

    public function test_headers_without_an_ascii_form_are_compared_as_written(): void
    {
        $this->assertNotSame('', SpreadsheetHeader::normalise('📧'));
        $this->assertNotSame(SpreadsheetHeader::normalise('📧'), SpreadsheetHeader::normalise('📞'));
    }
}
