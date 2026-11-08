<?php

declare(strict_types=1);

namespace App\Application\QrCodes;

use App\Domain\QrCodes\Exceptions\InvalidQrCodeOptionsException;

/**
 * A #RRGGBB colour of a QR code, with the WCAG relative luminance used to check contrast.
 */
final readonly class HexColor
{
    /**
     * @param  int<0, 255>  $red
     * @param  int<0, 255>  $green
     * @param  int<0, 255>  $blue
     */
    private function __construct(
        public int $red,
        public int $green,
        public int $blue,
    ) {}

    public static function fromString(string $value): self
    {
        if (preg_match('/\A#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})\z/', $value, $matches) !== 1) {
            throw InvalidQrCodeOptionsException::because(sprintf("'%s' is not a #RRGGBB colour", $value));
        }

        return new self(self::channel($matches[1]), self::channel($matches[2]), self::channel($matches[3]));
    }

    public function hex(): string
    {
        return sprintf('#%02X%02X%02X', $this->red, $this->green, $this->blue);
    }

    /**
     * WCAG 2 relative luminance, from 0 (black) to 1 (white).
     */
    public function relativeLuminance(): float
    {
        return 0.2126 * $this->linear($this->red)
            + 0.7152 * $this->linear($this->green)
            + 0.0722 * $this->linear($this->blue);
    }

    /**
     * WCAG 2 contrast ratio, from 1 (same luminance) to 21 (black on white), in either order.
     */
    public function contrastRatio(self $other): float
    {
        $lighter = max($this->relativeLuminance(), $other->relativeLuminance());
        $darker = min($this->relativeLuminance(), $other->relativeLuminance());

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * @return int<0, 255>
     */
    private static function channel(string $twoHexDigits): int
    {
        return max(0, min(255, (int) hexdec($twoHexDigits)));
    }

    private function linear(int $channel): float
    {
        $value = $channel / 255;

        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
}
