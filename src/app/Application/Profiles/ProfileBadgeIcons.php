<?php

declare(strict_types=1);

namespace App\Application\Profiles;

/**
 * The icons a profile badge may have (core#776): the keys of `profileBadgeIconMap` in
 * `resources/js/utils/icons.ts`, which a test keeps equal to this list. A closed list keeps the
 * browser's bundle small. A badge naming any other icon is left out (with a warning); modules check
 * theirs in their tests with isKnown().
 */
final class ProfileBadgeIcons
{
    /** Lucide names in kebab case, sorted. */
    public const array NAMES = [
        'award',
        'book-open',
        'calendar-check',
        'castle',
        'compass',
        'crown',
        'dices',
        'feather',
        'flag',
        'flame',
        'gem',
        'hand-heart',
        'handshake',
        'heart',
        'hourglass',
        'map',
        'map-pin',
        'medal',
        'message-square',
        'mountain',
        'puzzle',
        'rocket',
        'scroll-text',
        'shield',
        'sparkles',
        'sprout',
        'star',
        'sword',
        'swords',
        'target',
        'ticket',
        'trophy',
        'users',
        'vote',
        'wand-sparkles',
        'zap',
    ];

    public static function isKnown(string $icon): bool
    {
        return in_array($icon, self::NAMES, true);
    }
}
