<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidPriceException;
use App\Domain\ValueObjects\Price;
use PHPUnit\Framework\TestCase;

final class PriceTest extends TestCase
{
    public function test_it_creates_valid_price(): void
    {
        $price = new Price(10.50);

        $this->assertEquals(10.50, $price->value);
    }

    public function test_it_throws_exception_for_negative_price(): void
    {
        $this->expectException(InvalidPriceException::class);

        new Price(-5.00);
    }

    public function test_it_detects_free_price(): void
    {
        $price = new Price(0.0);

        $this->assertTrue($price->isFree());
    }

    public function test_it_detects_non_free_price(): void
    {
        $price = new Price(15.99);

        $this->assertFalse($price->isFree());
    }
}
