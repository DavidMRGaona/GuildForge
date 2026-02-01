<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Color;

use App\Application\Services\ColorPaletteGeneratorInterface;
use App\Domain\ValueObjects\ColorPalette;
use App\Domain\ValueObjects\HexColor;

final class OklchColorPaletteGenerator implements ColorPaletteGeneratorInterface
{
    /**
     * Lightness scale for each shade.
     * Shade 600 is anchored to the base color.
     */
    private const array LIGHTNESS_SCALE = [
        50 => 0.975,
        100 => 0.94,
        200 => 0.88,
        300 => 0.80,
        400 => 0.70,
        500 => 0.60,
        600 => 0.50,  // Base color anchored here
        700 => 0.40,
        800 => 0.32,  // Elevated surfaces in dark mode
        900 => 0.25,  // Surface/muted in dark mode
        950 => 0.18,  // Page background in dark mode
    ];

    /**
     * Chroma (saturation) curve relative to base.
     * Extremes are desaturated for better appearance.
     */
    private const array CHROMA_SCALE = [
        50 => 0.60,
        100 => 0.70,
        200 => 0.80,
        300 => 0.90,
        400 => 1.00,
        500 => 1.00,
        600 => 0.95,
        700 => 0.85,
        800 => 0.70,
        900 => 0.55,
        950 => 0.40,
    ];

    /**
     * Thresholds for warning detection.
     */
    private const float NEAR_WHITE_THRESHOLD = 0.95;

    private const float NEAR_BLACK_THRESHOLD = 0.15;

    private const float GRAYSCALE_THRESHOLD = 0.02;

    private const float HIGH_SATURATION_THRESHOLD = 0.20;

    public function generate(string $name, HexColor $baseColor): ColorPalette
    {
        $baseOklch = $baseColor->toOklch();

        // Calculate the chroma scaling factor based on base color
        $baseChroma = $baseOklch['c'];
        $baseLightness = $baseOklch['l'];

        $shades = [];

        foreach (self::LIGHTNESS_SCALE as $shade => $targetLightness) {
            // For shade 600, use the base color directly to preserve it exactly
            if ($shade === 600) {
                $shades[$shade] = $baseColor;

                continue;
            }

            $chromaFactor = self::CHROMA_SCALE[$shade];

            // Apply chroma curve
            $chroma = $baseChroma * $chromaFactor;

            // Apply hue rotation for warm colors on dark shades
            $hue = $this->adjustHue($baseOklch['h'], $shade);

            // Clamp chroma to valid range
            $chroma = max(0, min(0.4, $chroma));

            $shades[$shade] = HexColor::fromOklch($targetLightness, $chroma, $hue);
        }

        return new ColorPalette($name, $shades);
    }

    public function generateNeutral(string $name, HexColor $accentColor): ColorPalette
    {
        $accentOklch = $accentColor->toOklch();

        $shades = [];

        foreach (self::LIGHTNESS_SCALE as $shade => $targetLightness) {
            // Very low chroma for neutral, but keep a hint of the accent hue
            $chroma = 0.01 * self::CHROMA_SCALE[$shade];

            $shades[$shade] = HexColor::fromOklch($targetLightness, $chroma, $accentOklch['h']);
        }

        return new ColorPalette($name, $shades);
    }

    public function detectWarnings(HexColor $color): array
    {
        $oklch = $color->toOklch();
        $warnings = [];

        if ($oklch['l'] > self::NEAR_WHITE_THRESHOLD) {
            $warnings[] = 'near_white';
        }

        if ($oklch['l'] < self::NEAR_BLACK_THRESHOLD) {
            $warnings[] = 'near_black';
        }

        if ($oklch['c'] < self::GRAYSCALE_THRESHOLD) {
            $warnings[] = 'grayscale';
        }

        if ($oklch['c'] > self::HIGH_SATURATION_THRESHOLD) {
            $warnings[] = 'highly_saturated';
        }

        return $warnings;
    }

    /**
     * Adjust hue for warm colors on dark shades.
     * Prevents dark oranges/yellows from looking "muddy".
     */
    private function adjustHue(float $hue, int $shade): float
    {
        // Only adjust for dark shades (800-950) and warm hues (< 60°)
        if ($shade < 800 || $hue >= 60) {
            return $hue;
        }

        // Rotate hue slightly towards red for warmer appearance
        $rotationFactor = match ($shade) {
            800 => 3,
            900 => 4,
            950 => 5,
            default => 0,
        };

        return $hue + $rotationFactor;
    }
}
