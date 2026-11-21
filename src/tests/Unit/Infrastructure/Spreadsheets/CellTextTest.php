<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Spreadsheets;

use App\Infrastructure\Spreadsheets\CellText;
use DateInterval;
use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateIntervalCell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CellTextTest extends TestCase
{
    /**
     * @return iterable<string, array{Cell, string}>
     */
    public static function cells(): iterable
    {
        yield 'trimmed text' => [new StringCell("\u{A0} Ana \t", null), 'Ana'];
        yield 'empty' => [new EmptyCell(null, null), ''];
        yield 'integer' => [new NumericCell(42, null), '42'];
        yield 'integral float' => [new NumericCell(25.0, null), '25'];
        yield 'decimal' => [new NumericCell(1234.5, null), '1234.5'];
        yield 'binary noise' => [new NumericCell(0.1 + 0.2, null), '0.3'];
        yield 'small' => [new NumericCell(0.00001, null), '0.00001'];
        yield 'large' => [new NumericCell(1e20, null), '100000000000000000000'];
        yield 'negative' => [new NumericCell(-3.75, null), '-3.75'];
        yield 'negative zero' => [new NumericCell(-0.0, null), '0'];
        yield 'date' => [new DateTimeCell(new DateTimeImmutable('2024-03-05 00:00:00'), null), '2024-03-05'];
        yield 'date and time' => [new DateTimeCell(new DateTimeImmutable('2024-03-05 09:07:00'), null), '2024-03-05 09:07:00'];
        yield 'duration' => [new DateIntervalCell(new DateInterval('PT26H5M'), null), '26:05:00'];
        yield 'formula with its result' => [new FormulaCell('=1+1', null, 2), '2'];
        yield 'formula with a text result' => [new FormulaCell('="=a"', null, '=a'), '=a'];
        yield 'text starting with an equals sign' => [new FormulaCell('=texto', null), '=texto'];
        yield 'error' => [new ErrorCell('#N/A', null), ''];
    }

    #[DataProvider('cells')]
    public function test_every_cell_becomes_plain_text(Cell $cell, string $expected): void
    {
        $this->assertSame($expected, (new CellText)->of($cell));
    }
}
