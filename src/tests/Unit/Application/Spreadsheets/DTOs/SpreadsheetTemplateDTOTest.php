<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Spreadsheets\DTOs;

use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpreadsheetTemplateDTOTest extends TestCase
{
    public function test_a_text_may_fill_a_whole_cell(): void
    {
        $template = new SpreadsheetTemplateDTO(['Nombre'], [[str_repeat('á', 32_767)]]);

        $this->assertSame(32_767, mb_strlen((string) $template->rows[0][0]));
    }

    public function test_it_keeps_headers_rows_and_sheet_name(): void
    {
        $template = new SpreadsheetTemplateDTO(['Nombre', 'Cuota'], [['Ana', 25.5], ['Luis']], 'Socios');

        $this->assertSame(['Nombre', 'Cuota'], $template->headers);
        $this->assertSame([['Ana', 25.5], ['Luis']], $template->rows);
        $this->assertSame('Socios', $template->sheetName);
    }

    /**
     * @return iterable<string, array{list<mixed>, list<mixed>, string}>
     */
    public static function invalidTemplates(): iterable
    {
        yield 'no headers' => [[], [], 'Hoja1'];
        yield 'blank header' => [['Nombre', '  '], [], 'Hoja1'];
        yield 'header that is not text' => [['Nombre', 3], [], 'Hoja1'];
        yield 'repeated header ignoring case and accents' => [['Teléfono', 'TELEFONO'], [], 'Hoja1'];
        yield 'row wider than the header' => [['Nombre'], [['Ana', 'sobra']], 'Hoja1'];
        yield 'row that is not a list' => [['Nombre'], [['nombre' => 'Ana']], 'Hoja1'];
        yield 'value that is not a scalar' => [['Nombre'], [[['Ana']]], 'Hoja1'];
        yield 'boolean value' => [['Activo'], [[true]], 'Hoja1'];
        yield 'text longer than a cell' => [['Nombre'], [[str_repeat('a', 32_768)]], 'Hoja1'];
        yield 'header longer than a cell' => [[str_repeat('a', 32_768)], [], 'Hoja1'];
        yield 'not a number' => [['Cuota'], [[NAN]], 'Hoja1'];
        yield 'infinite' => [['Cuota'], [[INF]], 'Hoja1'];
        yield 'text not in UTF-8' => [['Nombre'], [["Jos\xE9"]], 'Hoja1'];
        yield 'header not in UTF-8' => [["Tel\xE9fono"], [], 'Hoja1'];
        yield 'sheet name not in UTF-8' => [['Nombre'], [], "Hoj\xE1"];
        yield 'empty sheet name' => [['Nombre'], [], ''];
        yield 'sheet name too long' => [['Nombre'], [], str_repeat('a', 32)];
        yield 'sheet name with a forbidden character' => [['Nombre'], [], 'Socios/2025'];
        yield 'sheet name starting with an apostrophe' => [['Nombre'], [], "'Socios"];
    }

    /**
     * @param  list<mixed>  $headers
     * @param  list<mixed>  $rows
     */
    #[DataProvider('invalidTemplates')]
    public function test_it_refuses_invalid_templates(array $headers, array $rows, string $sheetName): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpreadsheetTemplateDTO($headers, $rows, $sheetName);
    }
}
