<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\ColorPalette;
use App\Domain\ValueObjects\HexColor;
use PHPUnit\Framework\TestCase;

final class ColorPaletteTest extends TestCase
{
    private const array SHADE_KEYS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    public function test_it_creates_palette_with_all_shades(): void
    {
        $shades = $this->createShades();

        $palette = new ColorPalette('primary', $shades);

        $this->assertCount(11, $palette->shades());
    }

    public function test_it_has_correct_shade_keys(): void
    {
        $shades = $this->createShades();

        $palette = new ColorPalette('primary', $shades);

        $this->assertEquals(self::SHADE_KEYS, array_keys($palette->shades()));
    }

    public function test_it_returns_shade_by_key(): void
    {
        $shades = $this->createShades();
        $expected = new HexColor('#D97706');

        $palette = new ColorPalette('primary', $shades);

        $this->assertTrue($expected->equals($palette->shade(600)));
    }

    public function test_it_returns_null_for_invalid_shade(): void
    {
        $shades = $this->createShades();

        $palette = new ColorPalette('primary', $shades);

        $this->assertNull($palette->shade(150));
    }

    public function test_it_stores_palette_name(): void
    {
        $shades = $this->createShades();

        $palette = new ColorPalette('primary', $shades);

        $this->assertEquals('primary', $palette->name());
    }

    public function test_it_generates_css_variables(): void
    {
        $shades = $this->createShades();

        $palette = new ColorPalette('primary', $shades);
        $css = $palette->toCssVariables();

        $this->assertStringContainsString('--color-primary-50:', $css);
        $this->assertStringContainsString('--color-primary-600:', $css);
        $this->assertStringContainsString('--color-primary-950:', $css);
    }

    public function test_css_variables_contain_hex_values(): void
    {
        $shades = $this->createShades();

        $palette = new ColorPalette('primary', $shades);
        $css = $palette->toCssVariables();

        $this->assertMatchesRegularExpression('/--color-primary-600:\s*#[A-F0-9]{6};/', $css);
    }

    public function test_it_generates_css_variables_with_custom_prefix(): void
    {
        $shades = $this->createShades();

        $palette = new ColorPalette('accent', $shades);
        $css = $palette->toCssVariables();

        $this->assertStringContainsString('--color-accent-50:', $css);
        $this->assertStringContainsString('--color-accent-950:', $css);
    }

    public function test_it_generates_oklch_css_variables(): void
    {
        $shades = $this->createShades();

        $palette = new ColorPalette('primary', $shades);
        $css = $palette->toOklchCssVariables();

        $this->assertStringContainsString('--color-primary-50:', $css);
        $this->assertMatchesRegularExpression('/oklch\([0-9.]+\s+[0-9.]+\s+[0-9.]+\)/', $css);
    }

    public function test_it_returns_base_color(): void
    {
        $shades = $this->createShades();

        $palette = new ColorPalette('primary', $shades);

        $this->assertInstanceOf(HexColor::class, $palette->baseColor());
        $this->assertEquals('#D97706', $palette->baseColor()->value);
    }

    public function test_it_checks_equality(): void
    {
        $shades1 = $this->createShades();
        $shades2 = $this->createShades();

        $palette1 = new ColorPalette('primary', $shades1);
        $palette2 = new ColorPalette('primary', $shades2);

        $this->assertTrue($palette1->equals($palette2));
    }

    public function test_it_checks_inequality_by_name(): void
    {
        $shades1 = $this->createShades();
        $shades2 = $this->createShades();

        $palette1 = new ColorPalette('primary', $shades1);
        $palette2 = new ColorPalette('accent', $shades2);

        $this->assertFalse($palette1->equals($palette2));
    }

    public function test_it_checks_inequality_by_shades(): void
    {
        $shades1 = $this->createShades();
        $shades2 = $this->createDifferentShades();

        $palette1 = new ColorPalette('primary', $shades1);
        $palette2 = new ColorPalette('primary', $shades2);

        $this->assertFalse($palette1->equals($palette2));
    }

    public function test_throws_exception_for_missing_shades(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $incompleteShades = [
            50 => new HexColor('#FEF3C7'),
            100 => new HexColor('#FDE68A'),
        ];

        new ColorPalette('primary', $incompleteShades);
    }

    public function test_throws_exception_for_invalid_shade_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $invalidShades = [
            50 => new HexColor('#FEF3C7'),
            100 => new HexColor('#FDE68A'),
            200 => new HexColor('#FCD34D'),
            300 => new HexColor('#FBBF24'),
            400 => new HexColor('#F59E0B'),
            500 => new HexColor('#D97706'),
            600 => new HexColor('#D97706'),
            700 => new HexColor('#B45309'),
            800 => new HexColor('#92400E'),
            900 => new HexColor('#78350F'),
            999 => new HexColor('#451A03'), // Invalid key
        ];

        new ColorPalette('primary', $invalidShades);
    }

    public function test_to_array_returns_hex_values(): void
    {
        $shades = $this->createShades();

        $palette = new ColorPalette('primary', $shades);
        $array = $palette->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('shades', $array);
        $this->assertEquals('primary', $array['name']);
        $this->assertCount(11, $array['shades']);
        $this->assertEquals('#D97706', $array['shades'][600]);
    }

    /**
     * @return array<int, HexColor>
     */
    private function createShades(): array
    {
        return [
            50 => new HexColor('#FEF3C7'),
            100 => new HexColor('#FDE68A'),
            200 => new HexColor('#FCD34D'),
            300 => new HexColor('#FBBF24'),
            400 => new HexColor('#F59E0B'),
            500 => new HexColor('#D97706'),
            600 => new HexColor('#D97706'),
            700 => new HexColor('#B45309'),
            800 => new HexColor('#92400E'),
            900 => new HexColor('#78350F'),
            950 => new HexColor('#451A03'),
        ];
    }

    /**
     * @return array<int, HexColor>
     */
    private function createDifferentShades(): array
    {
        return [
            50 => new HexColor('#EFF6FF'),
            100 => new HexColor('#DBEAFE'),
            200 => new HexColor('#BFDBFE'),
            300 => new HexColor('#93C5FD'),
            400 => new HexColor('#60A5FA'),
            500 => new HexColor('#3B82F6'),
            600 => new HexColor('#2563EB'),
            700 => new HexColor('#1D4ED8'),
            800 => new HexColor('#1E40AF'),
            900 => new HexColor('#1E3A8A'),
            950 => new HexColor('#172554'),
        ];
    }
}
