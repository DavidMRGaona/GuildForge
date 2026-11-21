<?php

declare(strict_types=1);

namespace Tests\Feature\Spreadsheets;

use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use App\Application\Spreadsheets\Services\SpreadsheetReaderInterface;
use App\Application\Spreadsheets\Services\SpreadsheetWriterInterface;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Domain\Spreadsheets\SpreadsheetReadFailure;
use Tests\TestCase;

final class SpreadsheetContractTest extends TestCase
{
    public function test_modules_read_and_write_through_the_container(): void
    {
        $bytes = $this->app->make(SpreadsheetWriterInterface::class)
            ->write(new SpreadsheetTemplateDTO(['Nombre', 'Email'], [['Ana', 'ana@example.org']]));
        $path = (string) tempnam(sys_get_temp_dir(), 'gf-sheet-test-');
        file_put_contents($path, $bytes);

        try {
            $sheet = $this->app->make(SpreadsheetReaderInterface::class)->read($path);
        } finally {
            unlink($path);
        }

        $this->assertSame(['Nombre', 'Email'], $sheet->headers);
        $this->assertSame(['Nombre' => 'Ana', 'Email' => 'ana@example.org'], $sheet->rows[0]->values);
    }

    public function test_every_failure_has_a_message_in_each_language(): void
    {
        foreach (['es', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach (SpreadsheetReadFailure::cases() as $reason) {
                $message = (new SpreadsheetReadException($reason, limit: 10, column: 2))->userMessage();

                $this->assertStringNotContainsString('spreadsheets.', $message, $locale.' '.$reason->value);
                $this->assertStringNotContainsString(':limit', $message, $locale.' '.$reason->value);
                $this->assertStringNotContainsString(':column', $message, $locale.' '.$reason->value);
            }
        }
    }

    public function test_messages_name_the_limit(): void
    {
        app()->setLocale('es');

        $this->assertStringContainsString('1000', (new SpreadsheetReadException(SpreadsheetReadFailure::TooManyRows, limit: 1000))->userMessage());
        $this->assertStringContainsString('2 MB', (new SpreadsheetReadException(SpreadsheetReadFailure::TooLarge, limit: 2 * 1024 * 1024))->userMessage());
        $this->assertStringContainsString('500 KB', (new SpreadsheetReadException(SpreadsheetReadFailure::TooLarge, limit: 500 * 1024))->userMessage());
        $this->assertStringContainsString('3', (new SpreadsheetReadException(SpreadsheetReadFailure::DuplicateHeaders, column: 3))->userMessage());
    }
}
