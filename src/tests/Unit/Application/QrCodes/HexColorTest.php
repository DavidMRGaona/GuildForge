<?php

declare(strict_types=1);

namespace Tests\Unit\Application\QrCodes;

use App\Application\QrCodes\HexColor;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeOptionsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HexColorTest extends TestCase
{
    public function test_it_reads_the_channels_and_normalises_the_hex(): void
    {
        $color = HexColor::fromString('#8b00fF');

        $this->assertSame(0x8B, $color->red);
        $this->assertSame(0x00, $color->green);
        $this->assertSame(0xFF, $color->blue);
        $this->assertSame('#8B00FF', $color->hex());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidColors(): iterable
    {
        yield 'empty' => [''];
        yield 'without hash' => ['000000'];
        yield 'short form' => ['#000'];
        yield 'with alpha' => ['#000000FF'];
        yield 'not hexadecimal' => ['#GGGGGG'];
        yield 'named colour' => ['black'];
        yield 'trailing newline' => ["#000000\n"];
    }

    #[DataProvider('invalidColors')]
    public function test_it_rejects_anything_but_six_digit_hex(string $value): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        HexColor::fromString($value);
    }

    public function test_black_on_white_has_the_maximum_contrast(): void
    {
        $black = HexColor::fromString('#000000');
        $white = HexColor::fromString('#FFFFFF');

        $this->assertEqualsWithDelta(0.0, $black->relativeLuminance(), 0.0001);
        $this->assertEqualsWithDelta(1.0, $white->relativeLuminance(), 0.0001);
        $this->assertEqualsWithDelta(21.0, $black->contrastRatio($white), 0.0001);
        $this->assertEqualsWithDelta(21.0, $white->contrastRatio($black), 0.0001);
    }

    public function test_it_computes_the_wcag_contrast_ratio(): void
    {
        // #767676 on white is the classic 4.54:1 WCAG AA boundary
        $grey = HexColor::fromString('#767676');
        $white = HexColor::fromString('#FFFFFF');

        $this->assertEqualsWithDelta(4.54, $grey->contrastRatio($white), 0.01);
    }
}
