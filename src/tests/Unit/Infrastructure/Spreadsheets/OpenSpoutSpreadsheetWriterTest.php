<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Spreadsheets;

use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use App\Application\Spreadsheets\Enums\SpreadsheetFormat;
use App\Infrastructure\Spreadsheets\OpenSpoutSpreadsheetReader;
use App\Infrastructure\Spreadsheets\OpenSpoutSpreadsheetWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class OpenSpoutSpreadsheetWriterTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_it_writes_a_template_the_reader_reads_back(): void
    {
        $bytes = (new OpenSpoutSpreadsheetWriter)->write(new SpreadsheetTemplateDTO(
            headers: ['Nombre', 'Email', 'Fecha de alta', 'Cuota'],
            rows: [['Ana Pérez', 'ana@example.org', '2025-01-31', 25.5], ['Luis', null, null, 30]],
            sheetName: 'Socios',
        ));

        $this->assertStringStartsWith("PK\x03\x04", $bytes);

        $sheet = (new OpenSpoutSpreadsheetReader)->read($this->store($bytes));

        $this->assertSame(SpreadsheetFormat::Xlsx, $sheet->format);
        $this->assertSame(['Nombre', 'Email', 'Fecha de alta', 'Cuota'], $sheet->headers);
        $this->assertSame(
            ['Nombre' => 'Ana Pérez', 'Email' => 'ana@example.org', 'Fecha de alta' => '2025-01-31', 'Cuota' => '25.5'],
            $sheet->rows[0]->values,
        );
        $this->assertSame(['Nombre' => 'Luis', 'Email' => '', 'Fecha de alta' => '', 'Cuota' => '30'], $sheet->rows[1]->values);
    }

    public function test_a_template_may_have_only_the_header(): void
    {
        $bytes = (new OpenSpoutSpreadsheetWriter)->write(new SpreadsheetTemplateDTO(headers: ['Nombre']));

        $this->assertStringContainsString('Nombre', $this->entry($bytes, 'xl/worksheets/sheet1.xml'));
    }

    public function test_text_that_looks_like_a_formula_stays_text(): void
    {
        $bytes = (new OpenSpoutSpreadsheetWriter)->write(new SpreadsheetTemplateDTO(
            headers: ['Nombre'],
            rows: [['=HYPERLINK("https://evil.example.com","Pulsa")']],
        ));

        $this->assertStringNotContainsString('<f>', $this->entry($bytes, 'xl/worksheets/sheet1.xml'));

        $sheet = (new OpenSpoutSpreadsheetReader)->read($this->store($bytes));
        $this->assertSame('=HYPERLINK("https://evil.example.com","Pulsa")', $sheet->rows[0]->value('Nombre'));
    }

    public function test_the_header_is_bold_and_stays_in_view(): void
    {
        $bytes = (new OpenSpoutSpreadsheetWriter)->write(new SpreadsheetTemplateDTO(headers: ['Nombre', 'Email'], rows: [['Ana', 'ana@example.org']]));

        $sheetXml = $this->entry($bytes, 'xl/worksheets/sheet1.xml');
        $this->assertMatchesRegularExpression('/<pane[^>]*ySplit="1"[^>]*state="frozen"/', $sheetXml);
        $this->assertStringContainsString('<b/>', $this->entry($bytes, 'xl/styles.xml'));
        $this->assertStringContainsString('<col ', $sheetXml, 'Columns get a width that fits their text');
        $this->assertStringContainsString('name="Hoja1"', $this->entry($bytes, 'xl/workbook.xml'));
    }

    private function entry(string $bytes, string $name): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->store($bytes)));
        $contents = $zip->getFromName($name);
        $zip->close();
        $this->assertIsString($contents);

        return $contents;
    }

    private function store(string $bytes): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'gf-sheet-test-');
        file_put_contents($path, $bytes);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
