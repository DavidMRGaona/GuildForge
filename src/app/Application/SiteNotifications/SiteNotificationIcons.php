<?php

declare(strict_types=1);

namespace App\Application\SiteNotifications;

/**
 * The icons the bell draws (core#667): the keys of `mailSectionIconMap` and
 * `siteNotificationIconMap` in `resources/js/utils/icons.ts`, which a test keeps equal to this
 * list. A notice may name any other icon in kebab case, but the bell shows it with its default
 * icon and the channel warns; modules check theirs in their tests with isKnown().
 */
final class SiteNotificationIcons
{
    /** Lucide names in kebab case, sorted. */
    public const array NAMES = [
        'at-sign',
        'ban',
        'bell',
        'book-open',
        'building-2',
        'calendar-check',
        'calendar-days',
        'chart-bar',
        'circle-check',
        'circle-user-round',
        'clipboard-check',
        'clipboard-list',
        'credit-card',
        'dices',
        'file-text',
        'flag',
        'handshake',
        'heart',
        'id-card',
        'images',
        'mail',
        'map-pin',
        'megaphone',
        'message-circle',
        'message-square',
        'newspaper',
        'quote',
        'settings-2',
        'shield-check',
        'swords',
        'thumbs-up',
        'ticket',
        'trophy',
        'user-plus',
        'users',
        'vote',
    ];

    public static function isKnown(string $icon): bool
    {
        return in_array($icon, self::NAMES, true);
    }
}
