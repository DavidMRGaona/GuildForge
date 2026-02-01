<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidHexColorException;
use Stringable;

final readonly class HexColor implements Stringable
{
    private const string PATTERN_FULL = '/^#?([A-Fa-f0-9]{6})$/';

    private const string PATTERN_SHORT = '/^#?([A-Fa-f0-9]{3})$/';

    public string $value;

    public function __construct(string $value)
    {
        $this->value = $this->normalize($value);
    }

    /**
     * Create HexColor from OKLCH values.
     */
    public static function fromOklch(float $l, float $c, float $h): self
    {
        $rgb = self::oklchToRgb($l, $c, $h);

        return self::fromRgb($rgb['r'], $rgb['g'], $rgb['b']);
    }

    /**
     * Create HexColor from RGB values.
     */
    public static function fromRgb(int $r, int $g, int $b): self
    {
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));

        return new self(sprintf('#%02X%02X%02X', $r, $g, $b));
    }

    /**
     * Convert to RGB array.
     *
     * @return array{r: int, g: int, b: int}
     */
    public function toRgb(): array
    {
        $hex = ltrim($this->value, '#');

        return [
            'r' => (int) hexdec(substr($hex, 0, 2)),
            'g' => (int) hexdec(substr($hex, 2, 2)),
            'b' => (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Convert to HSL array.
     *
     * @return array{h: float, s: float, l: float}
     */
    public function toHsl(): array
    {
        $rgb = $this->toRgb();
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return ['h' => 0.0, 's' => 0.0, 'l' => $l * 100];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6,
            $g => (($b - $r) / $d + 2) / 6,
            default => (($r - $g) / $d + 4) / 6,
        };

        return [
            'h' => $h * 360,
            's' => $s * 100,
            'l' => $l * 100,
        ];
    }

    /**
     * Convert to OKLCH array.
     *
     * @return array{l: float, c: float, h: float}
     */
    public function toOklch(): array
    {
        $rgb = $this->toRgb();

        // Convert to linear RGB
        $linearR = self::srgbToLinear($rgb['r'] / 255);
        $linearG = self::srgbToLinear($rgb['g'] / 255);
        $linearB = self::srgbToLinear($rgb['b'] / 255);

        // Convert to OKLab
        $lab = self::linearRgbToOklab($linearR, $linearG, $linearB);

        // Convert OKLab to OKLCH
        $c = sqrt($lab['a'] ** 2 + $lab['b'] ** 2);
        $h = $c < 0.0001 ? 0 : rad2deg(atan2($lab['b'], $lab['a']));

        if ($h < 0) {
            $h += 360;
        }

        return [
            'l' => $lab['l'],
            'c' => $c,
            'h' => $h,
        ];
    }

    /**
     * Calculate relative luminance for WCAG contrast calculations.
     * Based on WCAG 2.1 formula.
     */
    public function relativeLuminance(): float
    {
        $rgb = $this->toRgb();

        $r = self::luminanceComponent($rgb['r'] / 255);
        $g = self::luminanceComponent($rgb['g'] / 255);
        $b = self::luminanceComponent($rgb['b'] / 255);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Calculate contrast ratio between this color and another.
     * Returns a value between 1 and 21.
     */
    public function contrastRatio(self $other): float
    {
        $l1 = $this->relativeLuminance();
        $l2 = $other->relativeLuminance();

        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Check if contrast meets WCAG AA standard (4.5:1 for normal text).
     */
    public function meetsWcagAA(self $other): bool
    {
        return $this->contrastRatio($other) >= 4.5;
    }

    /**
     * Check if contrast meets WCAG AAA standard (7:1 for normal text).
     */
    public function meetsWcagAAA(self $other): bool
    {
        return $this->contrastRatio($other) >= 7.0;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function normalize(string $value): string
    {
        if ($value === '') {
            throw InvalidHexColorException::empty();
        }

        // Try full hex format (6 chars)
        if (preg_match(self::PATTERN_FULL, $value, $matches)) {
            return '#' . strtoupper($matches[1]);
        }

        // Try shorthand format (3 chars)
        if (preg_match(self::PATTERN_SHORT, $value, $matches)) {
            $short = strtoupper($matches[1]);

            return '#' . $short[0] . $short[0] . $short[1] . $short[1] . $short[2] . $short[2];
        }

        throw InvalidHexColorException::invalidFormat($value);
    }

    private static function srgbToLinear(float $value): float
    {
        return $value <= 0.04045
            ? $value / 12.92
            : (($value + 0.055) / 1.055) ** 2.4;
    }

    private static function linearToSrgb(float $value): float
    {
        return $value <= 0.0031308
            ? $value * 12.92
            : 1.055 * ($value ** (1 / 2.4)) - 0.055;
    }

    private static function luminanceComponent(float $value): float
    {
        return $value <= 0.03928
            ? $value / 12.92
            : (($value + 0.055) / 1.055) ** 2.4;
    }

    /**
     * Convert linear RGB to OKLab color space.
     *
     * @return array{l: float, a: float, b: float}
     */
    private static function linearRgbToOklab(float $r, float $g, float $b): array
    {
        // Matrix multiplication: linear RGB to LMS
        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;

        // Cube root
        $lPrime = $l ** (1 / 3);
        $mPrime = $m ** (1 / 3);
        $sPrime = $s ** (1 / 3);

        // Matrix multiplication: LMS to OKLab
        return [
            'l' => 0.2104542553 * $lPrime + 0.7936177850 * $mPrime - 0.0040720468 * $sPrime,
            'a' => 1.9779984951 * $lPrime - 2.4285922050 * $mPrime + 0.4505937099 * $sPrime,
            'b' => 0.0259040371 * $lPrime + 0.7827717662 * $mPrime - 0.8086757660 * $sPrime,
        ];
    }

    /**
     * Convert OKLab to linear RGB.
     *
     * @return array{r: float, g: float, b: float}
     */
    private static function oklabToLinearRgb(float $l, float $a, float $b): array
    {
        // Matrix multiplication: OKLab to LMS'
        $lPrime = $l + 0.3963377774 * $a + 0.2158037573 * $b;
        $mPrime = $l - 0.1055613458 * $a - 0.0638541728 * $b;
        $sPrime = $l - 0.0894841775 * $a - 1.2914855480 * $b;

        // Cube
        $lms_l = $lPrime ** 3;
        $lms_m = $mPrime ** 3;
        $lms_s = $sPrime ** 3;

        // Matrix multiplication: LMS to linear RGB
        return [
            'r' => 4.0767416621 * $lms_l - 3.3077115913 * $lms_m + 0.2309699292 * $lms_s,
            'g' => -1.2684380046 * $lms_l + 2.6097574011 * $lms_m - 0.3413193965 * $lms_s,
            'b' => -0.0041960863 * $lms_l - 0.7034186147 * $lms_m + 1.7076147010 * $lms_s,
        ];
    }

    /**
     * Convert OKLCH to RGB.
     *
     * @return array{r: int, g: int, b: int}
     */
    private static function oklchToRgb(float $l, float $c, float $h): array
    {
        // Convert OKLCH to OKLab
        $hRad = deg2rad($h);
        $a = $c * cos($hRad);
        $b = $c * sin($hRad);

        // Convert OKLab to linear RGB
        $linearRgb = self::oklabToLinearRgb($l, $a, $b);

        // Convert linear RGB to sRGB
        $r = self::linearToSrgb($linearRgb['r']);
        $g = self::linearToSrgb($linearRgb['g']);
        $b = self::linearToSrgb($linearRgb['b']);

        // Clamp and convert to 0-255
        return [
            'r' => (int) round(max(0, min(1, $r)) * 255),
            'g' => (int) round(max(0, min(1, $g)) * 255),
            'b' => (int) round(max(0, min(1, $b)) * 255),
        ];
    }
}
