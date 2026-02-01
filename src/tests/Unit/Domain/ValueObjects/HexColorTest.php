<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidHexColorException;
use App\Domain\ValueObjects\HexColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HexColorTest extends TestCase
{
    public function test_it_creates_valid_hex_color_with_hash(): void
    {
        $color = new HexColor('#D97706');

        $this->assertEquals('#D97706', $color->value);
    }

    public function test_it_creates_valid_hex_color_without_hash(): void
    {
        $color = new HexColor('D97706');

        $this->assertEquals('#D97706', $color->value);
    }

    public function test_it_normalizes_lowercase_to_uppercase(): void
    {
        $color = new HexColor('#d97706');

        $this->assertEquals('#D97706', $color->value);
    }

    public function test_it_expands_shorthand_hex(): void
    {
        $color = new HexColor('#ABC');

        $this->assertEquals('#AABBCC', $color->value);
    }

    public function test_it_throws_exception_for_invalid_hex(): void
    {
        $this->expectException(InvalidHexColorException::class);

        new HexColor('not-a-color');
    }

    public function test_it_throws_exception_for_empty_value(): void
    {
        $this->expectException(InvalidHexColorException::class);

        new HexColor('');
    }

    public function test_it_converts_to_rgb(): void
    {
        $color = new HexColor('#D97706');

        $rgb = $color->toRgb();

        $this->assertEquals(217, $rgb['r']);
        $this->assertEquals(119, $rgb['g']);
        $this->assertEquals(6, $rgb['b']);
    }

    public function test_it_converts_black_to_rgb(): void
    {
        $color = new HexColor('#000000');

        $rgb = $color->toRgb();

        $this->assertEquals(0, $rgb['r']);
        $this->assertEquals(0, $rgb['g']);
        $this->assertEquals(0, $rgb['b']);
    }

    public function test_it_converts_white_to_rgb(): void
    {
        $color = new HexColor('#FFFFFF');

        $rgb = $color->toRgb();

        $this->assertEquals(255, $rgb['r']);
        $this->assertEquals(255, $rgb['g']);
        $this->assertEquals(255, $rgb['b']);
    }

    public function test_it_converts_to_hsl(): void
    {
        $color = new HexColor('#D97706');

        $hsl = $color->toHsl();

        // Amber color: ~32° hue, ~95% saturation, ~44% lightness
        $this->assertEqualsWithDelta(32.1, $hsl['h'], 0.5);
        $this->assertEqualsWithDelta(94.6, $hsl['s'], 0.5);
        $this->assertEqualsWithDelta(43.7, $hsl['l'], 0.5);
    }

    public function test_it_converts_gray_to_hsl(): void
    {
        $color = new HexColor('#808080');

        $hsl = $color->toHsl();

        $this->assertEquals(0, $hsl['h']);
        $this->assertEquals(0, $hsl['s']);
        $this->assertEqualsWithDelta(50.2, $hsl['l'], 0.5);
    }

    public function test_it_converts_to_oklch(): void
    {
        $color = new HexColor('#D97706');

        $oklch = $color->toOklch();

        // OKLCH values for amber: L ~0.66, C ~0.15, H ~60°
        $this->assertArrayHasKey('l', $oklch);
        $this->assertArrayHasKey('c', $oklch);
        $this->assertArrayHasKey('h', $oklch);
        $this->assertGreaterThan(0, $oklch['l']);
        $this->assertLessThan(1, $oklch['l']);
        $this->assertGreaterThan(0, $oklch['c']);
    }

    public function test_it_converts_white_to_oklch(): void
    {
        $color = new HexColor('#FFFFFF');

        $oklch = $color->toOklch();

        $this->assertEqualsWithDelta(1.0, $oklch['l'], 0.01);
        $this->assertEqualsWithDelta(0, $oklch['c'], 0.001);
    }

    public function test_it_converts_black_to_oklch(): void
    {
        $color = new HexColor('#000000');

        $oklch = $color->toOklch();

        $this->assertEqualsWithDelta(0, $oklch['l'], 0.01);
        $this->assertEqualsWithDelta(0, $oklch['c'], 0.001);
    }

    public function test_it_calculates_relative_luminance(): void
    {
        $white = new HexColor('#FFFFFF');
        $black = new HexColor('#000000');

        $this->assertEqualsWithDelta(1.0, $white->relativeLuminance(), 0.001);
        $this->assertEqualsWithDelta(0.0, $black->relativeLuminance(), 0.001);
    }

    public function test_it_calculates_contrast_ratio_white_on_black(): void
    {
        $white = new HexColor('#FFFFFF');
        $black = new HexColor('#000000');

        $contrast = $white->contrastRatio($black);

        $this->assertEqualsWithDelta(21.0, $contrast, 0.1);
    }

    public function test_it_calculates_contrast_ratio_is_symmetric(): void
    {
        $color1 = new HexColor('#D97706');
        $color2 = new HexColor('#1F2937');

        $contrast1 = $color1->contrastRatio($color2);
        $contrast2 = $color2->contrastRatio($color1);

        $this->assertEquals($contrast1, $contrast2);
    }

    public function test_meets_wcag_aa_returns_true_for_sufficient_contrast(): void
    {
        $white = new HexColor('#FFFFFF');
        $darkGray = new HexColor('#374151');

        $this->assertTrue($white->meetsWcagAA($darkGray));
    }

    public function test_meets_wcag_aa_returns_false_for_insufficient_contrast(): void
    {
        $lightGray = new HexColor('#D1D5DB');
        $white = new HexColor('#FFFFFF');

        $this->assertFalse($lightGray->meetsWcagAA($white));
    }

    public function test_meets_wcag_aaa_requires_higher_contrast(): void
    {
        $white = new HexColor('#FFFFFF');
        $gray = new HexColor('#6B7280');

        // 4.5:1 ratio passes AA but not AAA (7:1)
        $this->assertTrue($white->meetsWcagAA($gray));
        $this->assertFalse($white->meetsWcagAAA($gray));
    }

    public function test_equals_returns_true_for_same_color(): void
    {
        $color1 = new HexColor('#D97706');
        $color2 = new HexColor('#D97706');

        $this->assertTrue($color1->equals($color2));
    }

    public function test_equals_returns_true_for_same_color_different_format(): void
    {
        $color1 = new HexColor('#D97706');
        $color2 = new HexColor('d97706');

        $this->assertTrue($color1->equals($color2));
    }

    public function test_equals_returns_false_for_different_colors(): void
    {
        $color1 = new HexColor('#D97706');
        $color2 = new HexColor('#0EA5E9');

        $this->assertFalse($color1->equals($color2));
    }

    public function test_it_implements_stringable(): void
    {
        $color = new HexColor('#D97706');

        $this->assertEquals('#D97706', (string) $color);
    }

    public function test_from_oklch_creates_hex_color(): void
    {
        $color = HexColor::fromOklch(0.66, 0.15, 60);

        $this->assertInstanceOf(HexColor::class, $color);
        $this->assertMatchesRegularExpression('/^#[A-F0-9]{6}$/', $color->value);
    }

    public function test_from_oklch_roundtrip_preserves_approximate_values(): void
    {
        $original = new HexColor('#D97706');
        $oklch = $original->toOklch();
        $recreated = HexColor::fromOklch($oklch['l'], $oklch['c'], $oklch['h']);

        // Due to color space conversions, we allow small differences
        $originalRgb = $original->toRgb();
        $recreatedRgb = $recreated->toRgb();

        $this->assertEqualsWithDelta($originalRgb['r'], $recreatedRgb['r'], 2);
        $this->assertEqualsWithDelta($originalRgb['g'], $recreatedRgb['g'], 2);
        $this->assertEqualsWithDelta($originalRgb['b'], $recreatedRgb['b'], 2);
    }

    #[DataProvider('validHexColorsProvider')]
    public function test_it_accepts_valid_hex_formats(string $input, string $expected): void
    {
        $color = new HexColor($input);

        $this->assertEquals($expected, $color->value);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validHexColorsProvider(): array
    {
        return [
            'uppercase with hash' => ['#AABBCC', '#AABBCC'],
            'lowercase with hash' => ['#aabbcc', '#AABBCC'],
            'uppercase without hash' => ['AABBCC', '#AABBCC'],
            'lowercase without hash' => ['aabbcc', '#AABBCC'],
            'shorthand uppercase' => ['#ABC', '#AABBCC'],
            'shorthand lowercase' => ['#abc', '#AABBCC'],
            'shorthand without hash' => ['ABC', '#AABBCC'],
            'mixed case' => ['#AaBbCc', '#AABBCC'],
        ];
    }

    #[DataProvider('invalidHexColorsProvider')]
    public function test_it_rejects_invalid_hex_formats(string $input): void
    {
        $this->expectException(InvalidHexColorException::class);

        new HexColor($input);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHexColorsProvider(): array
    {
        return [
            'empty string' => [''],
            'invalid characters' => ['#GGHHII'],
            'too short' => ['#AB'],
            'too long' => ['#AABBCCDD'],
            'wrong length' => ['#ABCDE'],
            'text' => ['red'],
            'rgb format' => ['rgb(255,0,0)'],
            'special characters' => ['#AB!@#$'],
        ];
    }
}
