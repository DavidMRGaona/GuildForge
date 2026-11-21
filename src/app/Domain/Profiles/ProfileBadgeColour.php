<?php

declare(strict_types=1);

namespace App\Domain\Profiles;

/**
 * The colours a profile badge may have (core#776): a closed palette, so every badge reads well in
 * both colour schemes. The browser draws each one with the classes of `profileBadgeColourClasses`
 * in `resources/js/utils/profileBadges.ts` (a test keeps both lists equal): a strong shade with white
 * text in the light scheme and a light shade with dark text in the dark one, every pair with a
 * contrast of at least 4.5:1, so the label and the icon pass WCAG AA as text.
 */
enum ProfileBadgeColour: string
{
    case Slate = 'slate';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Green = 'green';
    case Teal = 'teal';
    case Sky = 'sky';
    case Blue = 'blue';
    case Violet = 'violet';
    case Pink = 'pink';
}
