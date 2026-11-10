<?php

declare(strict_types=1);

namespace App\Application\SiteNotifications\DTOs;

/**
 * One row of the notification centre as the person sees it: a notice, or the unread notices of
 * one group gathered, led by the newest.
 */
final readonly class SiteNotificationItemDTO
{
    /**
     * @param  string  $url  Path of the site it leads to (opening it goes through the core, which marks it read).
     * @param  int  $count  Notices gathered in the row (1 for a single one).
     * @param  string|null  $notifiedAt  Instant of its latest notice, ISO 8601 in the display zone.
     */
    public function __construct(
        public string $id,
        public string $category,
        public string $title,
        public ?string $body,
        public string $url,
        public ?string $icon,
        public int $count,
        public bool $unread,
        public ?string $notifiedAt,
    ) {}
}
