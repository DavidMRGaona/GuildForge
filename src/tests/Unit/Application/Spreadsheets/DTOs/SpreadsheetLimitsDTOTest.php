<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Spreadsheets\DTOs;

use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpreadsheetLimitsDTOTest extends TestCase
{
    public function test_it_has_prudent_defaults(): void
    {
        $limits = new SpreadsheetLimitsDTO;

        $this->assertSame(1000, $limits->maxRows);
        $this->assertSame(50, $limits->maxColumns);
        $this->assertSame(5 * 1024 * 1024, $limits->maxBytes);
    }

    /**
     * @return iterable<string, array{array<string, int>}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'no rows' => [['maxRows' => 0]];
        yield 'negative columns' => [['maxColumns' => -1]];
        yield 'no bytes' => [['maxBytes' => 0]];
    }

    /**
     * @param  array<string, int>  $arguments
     */
    #[DataProvider('invalidLimits')]
    public function test_every_limit_must_be_positive(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpreadsheetLimitsDTO(...$arguments);
    }
}
