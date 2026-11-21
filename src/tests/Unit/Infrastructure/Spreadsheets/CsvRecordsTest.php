<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Spreadsheets;

use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Domain\Spreadsheets\SpreadsheetReadFailure;
use App\Infrastructure\Spreadsheets\CsvRecords;
use PHPUnit\Framework\TestCase;

final class CsvRecordsTest extends TestCase
{
    public function test_it_splits_records_and_fields_with_rfc_4180_quotes(): void
    {
        $records = iterator_to_array(CsvRecords::read(
            "a;b;c\r\n\"x;y\nz\";\"he said \"\"hi\"\"\";dijo \"hola\"\n\n;;\rlast",
            ';',
            maxFields: 3,
            limit: 3,
        ));

        $this->assertSame([
            1 => ['a', 'b', 'c'],
            2 => ["x;y\nz", 'he said "hi"', 'dijo "hola"'],
            3 => [''],
            4 => ['', '', ''],
            5 => ['last'],
        ], $records);
    }

    public function test_an_unterminated_quote_takes_the_rest_of_the_text(): void
    {
        $this->assertSame([1 => ['a', "b;c\nd"]], iterator_to_array(CsvRecords::read("a;\"b;c\nd", ';', 10, 10)));
    }

    public function test_it_stops_as_soon_as_a_record_has_too_many_fields(): void
    {
        try {
            iterator_to_array(CsvRecords::read("a;b\n1;2;3;4\n", ';', maxFields: 3, limit: 2));
            $this->fail('Expected too_many_columns');
        } catch (SpreadsheetReadException $exception) {
            $this->assertSame(SpreadsheetReadFailure::TooManyColumns, $exception->reason);
            $this->assertSame(2, $exception->limit);
        }
    }
}
