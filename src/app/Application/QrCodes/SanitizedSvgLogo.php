<?php

declare(strict_types=1);

namespace App\Application\QrCodes;

/**
 * An SVG logo after SvgLogoSanitizer: markup without a size of its own (only a viewBox) and
 * its intrinsic size in CSS pixels, used only for its proportion.
 */
final readonly class SanitizedSvgLogo
{
    public function __construct(
        public string $contents,
        public float $width,
        public float $height,
    ) {}
}
