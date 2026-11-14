<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * The greys of every surface: the public site, the admin panel and emails.
 *
 * Fixed, whatever the site's accent colour: stone for the light shades, and for the dark theme a
 * neutral near-black page (950) under slightly warm surfaces (900), a neutral elevated grey (800)
 * and a soft border (700), as in current desktop apps in dark mode.
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
        700 => '#3b3b3b',
        800 => '#303030',
        900 => '#282624',
        950 => '#181818',
    ];

    public static function palette(): ColorPalette
    {
        return new ColorPalette(
            self::NAME,
            array_map(static fn (string $hex): HexColor => new HexColor($hex), self::SHADES),
        );
    }
}
