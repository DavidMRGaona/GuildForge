<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Spreadsheets\DTOs;

use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpreadsheetTemplateDTOTest extends TestCase
{
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
