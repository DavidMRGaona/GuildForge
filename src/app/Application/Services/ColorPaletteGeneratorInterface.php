<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\ValueObjects\ColorPalette;
use App\Domain\ValueObjects\HexColor;

interface ColorPaletteGeneratorInterface
{
    /**
     * Generate a color palette from a base color.
     */
    public function generate(string $name, HexColor $baseColor): ColorPalette;

    /**
     * Generate a neutral palette from an accent color.
     * The resulting palette will have very low saturation.
     */
    public function generateNeutral(string $name, HexColor $accentColor): ColorPalette;

    /**
     * Detect potential issues with a base color.
     *
     * @return array<string> Warning codes: 'near_white', 'near_black', 'grayscale', 'highly_saturated'
     */
    public function detectWarnings(HexColor $color): array;
}
