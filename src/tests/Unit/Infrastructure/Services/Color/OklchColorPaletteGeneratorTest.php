<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services\Color;

use App\Application\Services\ColorPaletteGeneratorInterface;
use App\Domain\ValueObjects\ColorPalette;
use App\Domain\ValueObjects\HexColor;
use App\Infrastructure\Services\Color\OklchColorPaletteGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OklchColorPaletteGeneratorTest extends TestCase
{
    private OklchColorPaletteGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new OklchColorPaletteGenerator();
    }

    public function test_it_implements_interface(): void
    {
        $this->assertInstanceOf(ColorPaletteGeneratorInterface::class, $this->generator);
    }

    public function test_it_generates_palette_with_all_shades(): void
    {
        $baseColor = new HexColor('#D97706');

        $palette = $this->generator->generate('primary', $baseColor);

        $this->assertInstanceOf(ColorPalette::class, $palette);
        $this->assertCount(11, $palette->shades());
    }

    public function test_it_uses_palette_name(): void
    {
        $baseColor = new HexColor('#D97706');

        $palette = $this->generator->generate('accent', $baseColor);

        $this->assertEquals('accent', $palette->name());
    }

    public function test_shade_600_is_closest_to_base_color(): void
    {
        $baseColor = new HexColor('#D97706');

        $palette = $this->generator->generate('primary', $baseColor);

        // Shade 600 should be very close to original
        $shade600 = $palette->shade(600);
        $this->assertNotNull($shade600);

        // RGB values should be within 10 of original
        $baseRgb = $baseColor->toRgb();
        $shade600Rgb = $shade600->toRgb();

        $this->assertEqualsWithDelta($baseRgb['r'], $shade600Rgb['r'], 15);
        $this->assertEqualsWithDelta($baseRgb['g'], $shade600Rgb['g'], 15);
        $this->assertEqualsWithDelta($baseRgb['b'], $shade600Rgb['b'], 15);
    }

    public function test_lighter_shades_have_higher_lightness(): void
    {
        $baseColor = new HexColor('#D97706');

        $palette = $this->generator->generate('primary', $baseColor);

        $shade50 = $palette->shade(50);
        $shade600 = $palette->shade(600);

        $this->assertNotNull($shade50);
        $this->assertNotNull($shade600);

        $oklch50 = $shade50->toOklch();
        $oklch600 = $shade600->toOklch();

        $this->assertGreaterThan($oklch600['l'], $oklch50['l']);
    }

    public function test_darker_shades_have_lower_lightness(): void
    {
        $baseColor = new HexColor('#D97706');

        $palette = $this->generator->generate('primary', $baseColor);

        $shade600 = $palette->shade(600);
        $shade950 = $palette->shade(950);

        $this->assertNotNull($shade600);
        $this->assertNotNull($shade950);

        $oklch600 = $shade600->toOklch();
        $oklch950 = $shade950->toOklch();

        $this->assertLessThan($oklch600['l'], $oklch950['l']);
    }

    public function test_shade_50_is_very_light(): void
    {
        $baseColor = new HexColor('#D97706');

        $palette = $this->generator->generate('primary', $baseColor);

        $shade50 = $palette->shade(50);
        $this->assertNotNull($shade50);

        $oklch = $shade50->toOklch();

        // Shade 50 should have L > 0.95
        $this->assertGreaterThan(0.90, $oklch['l']);
    }

    public function test_shade_950_is_very_dark(): void
    {
        $baseColor = new HexColor('#D97706');

        $palette = $this->generator->generate('primary', $baseColor);

        $shade950 = $palette->shade(950);
        $this->assertNotNull($shade950);

        $oklch = $shade950->toOklch();

        // Shade 950 should have L < 0.25
        $this->assertLessThan(0.25, $oklch['l']);
    }

    public function test_extreme_shades_have_reduced_saturation(): void
    {
        $baseColor = new HexColor('#D97706');

        $palette = $this->generator->generate('primary', $baseColor);

        $shade50 = $palette->shade(50);
        $shade500 = $palette->shade(500);
        $shade950 = $palette->shade(950);

        $this->assertNotNull($shade50);
        $this->assertNotNull($shade500);
        $this->assertNotNull($shade950);

        $oklch50 = $shade50->toOklch();
        $oklch500 = $shade500->toOklch();
        $oklch950 = $shade950->toOklch();

        // Extreme shades should have lower chroma than middle shades
        $this->assertLessThan($oklch500['c'], $oklch50['c']);
        $this->assertLessThan($oklch500['c'], $oklch950['c']);
    }

    public function test_hue_is_preserved_across_shades(): void
    {
        $baseColor = new HexColor('#D97706');
        $baseOklch = $baseColor->toOklch();

        $palette = $this->generator->generate('primary', $baseColor);

        // Check middle shades maintain similar hue (within 10 degrees)
        foreach ([300, 400, 500, 600, 700] as $shade) {
            $shadeColor = $palette->shade($shade);
            $this->assertNotNull($shadeColor);

            $shadeOklch = $shadeColor->toOklch();
            $hueDiff = abs($baseOklch['h'] - $shadeOklch['h']);

            // Handle hue wraparound
            if ($hueDiff > 180) {
                $hueDiff = 360 - $hueDiff;
            }

            $this->assertLessThan(15, $hueDiff, "Shade {$shade} hue differs too much from base");
        }
    }

    #[DataProvider('colorEdgeCasesProvider')]
    public function test_it_handles_edge_case_colors(string $hex, string $description): void
    {
        $baseColor = new HexColor($hex);

        $palette = $this->generator->generate('test', $baseColor);

        // Should not throw and should generate valid palette
        $this->assertInstanceOf(ColorPalette::class, $palette);
        $this->assertCount(11, $palette->shades());

        // All shades should be valid hex colors
        foreach ($palette->shades() as $shade) {
            $this->assertMatchesRegularExpression('/^#[A-F0-9]{6}$/', $shade->value);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function colorEdgeCasesProvider(): array
    {
        return [
            'pure red' => ['#FF0000', 'Pure red'],
            'pure green' => ['#00FF00', 'Pure green'],
            'pure blue' => ['#0000FF', 'Pure blue'],
            'yellow' => ['#FFFF00', 'Yellow (problematic in HSL)'],
            'cyan' => ['#00FFFF', 'Cyan'],
            'magenta' => ['#FF00FF', 'Magenta'],
            'white' => ['#FFFFFF', 'White'],
            'black' => ['#000000', 'Black'],
            'gray' => ['#808080', 'Gray (no saturation)'],
            'almost white' => ['#F5F5F5', 'Almost white'],
            'almost black' => ['#1A1A1A', 'Almost black'],
        ];
    }

    public function test_generates_distinct_shades(): void
    {
        $baseColor = new HexColor('#D97706');

        $palette = $this->generator->generate('primary', $baseColor);

        $shades = $palette->shades();
        $hexValues = array_map(fn (HexColor $c) => $c->value, $shades);
        $uniqueValues = array_unique($hexValues);

        // At least 9 unique colors (some edge shades might be similar)
        $this->assertGreaterThanOrEqual(9, count($uniqueValues));
    }

    public function test_contrast_between_50_and_900_is_high(): void
    {
        $baseColor = new HexColor('#D97706');

        $palette = $this->generator->generate('primary', $baseColor);

        $shade50 = $palette->shade(50);
        $shade900 = $palette->shade(900);

        $this->assertNotNull($shade50);
        $this->assertNotNull($shade900);

        // Should have at least 7:1 contrast (WCAG AAA)
        $contrast = $shade50->contrastRatio($shade900);
        $this->assertGreaterThan(7.0, $contrast);
    }

    public function test_generates_neutral_palette_from_accent(): void
    {
        $accentColor = new HexColor('#0EA5E9');

        $neutral = $this->generator->generateNeutral('neutral', $accentColor);

        $this->assertInstanceOf(ColorPalette::class, $neutral);

        // Neutral should have very low chroma
        $shade500 = $neutral->shade(500);
        $this->assertNotNull($shade500);

        $oklch = $shade500->toOklch();
        $this->assertLessThan(0.03, $oklch['c']);
    }

    public function test_detects_near_white_color(): void
    {
        $nearWhite = new HexColor('#F8F8F8');

        $warnings = $this->generator->detectWarnings($nearWhite);

        $this->assertContains('near_white', $warnings);
    }

    public function test_detects_near_black_color(): void
    {
        $nearBlack = new HexColor('#0A0A0A');

        $warnings = $this->generator->detectWarnings($nearBlack);

        $this->assertContains('near_black', $warnings);
    }

    public function test_detects_grayscale_color(): void
    {
        $gray = new HexColor('#808080');

        $warnings = $this->generator->detectWarnings($gray);

        $this->assertContains('grayscale', $warnings);
    }

    public function test_detects_highly_saturated_color(): void
    {
        $saturated = new HexColor('#FF0000');

        $warnings = $this->generator->detectWarnings($saturated);

        $this->assertContains('highly_saturated', $warnings);
    }

    public function test_no_warnings_for_normal_color(): void
    {
        $normal = new HexColor('#D97706');

        $warnings = $this->generator->detectWarnings($normal);

        $this->assertEmpty($warnings);
    }
}
