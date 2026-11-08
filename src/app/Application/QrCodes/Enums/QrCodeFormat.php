<?php

declare(strict_types=1);

namespace App\Application\QrCodes\Enums;

enum QrCodeFormat: string
{
    /** Vector, for print and for pages: scales to any size */
    case Svg = 'svg';

    /** Raster at the size asked, for downloads and places that take no SVG */
    case Png = 'png';

    public function mimeType(): string
    {
        return match ($this) {
            self::Svg => 'image/svg+xml',
            self::Png => 'image/png',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}
