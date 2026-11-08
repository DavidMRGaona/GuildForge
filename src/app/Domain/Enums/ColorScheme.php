<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Light or dark rendering, as in the CSS `prefers-color-scheme` media feature.
 */
enum ColorScheme: string
{
    case Light = 'light';
    case Dark = 'dark';
}
