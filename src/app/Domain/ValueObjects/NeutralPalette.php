<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * The greys of every surface: the public site, the admin panel and emails.
 *
 * Fixed and warm, whatever the site's accent colour: stone for the light shades and lighter,
 * warmer darks than stone for the dark theme backgrounds (page 950, surface 900, elevated 800).
 */
final class NeutralPalette
{
    public const string NAME = 'neutral';

    /** @var array<int, string> */
    public const array SHADES = [
        50 => '#fafaf9',
        100 => '#f5f5f4',
        200 => '#e7e5e4',
        300 => '#d6d3d1',
        400 => '#a8a29e',
        500 => '#78716c',
        600 => '#57534e',
        700 => '#44403c',
        800 => '#3d3936',
        900 => '#302d2a',
        950 => '#252220',
    ];

    public static function palette(): ColorPalette
    {
        return new ColorPalette(
            self::NAME,
            array_map(static fn (string $hex): HexColor => new HexColor($hex), self::SHADES),
        );
    }
}
