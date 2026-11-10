<?php

declare(strict_types=1);

namespace App\Domain\SiteNotifications\ValueObjects;

use DateTimeImmutable;

/**
 * One row of someone's notification centre: a notice, or the unread notices of a group.
 */
final readonly class StoredSiteNotice
{
    public function __construct(
        public string $id,
        public string $category,
        public string $title,
        public ?string $body,
        public string $url,
        public ?string $icon,
        public int $count,
        public ?DateTimeImmutable $readAt,
        public DateTimeImmutable $notifiedAt,
    ) {}

    public function isUnread(): bool
    {
        return ! $this->readAt instanceof DateTimeImmutable;
    }
}
