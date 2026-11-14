<?php

declare(strict_types=1);

namespace App\Domain\SiteNotifications\Repositories;

use App\Domain\SiteNotifications\ValueObjects\SiteNotice;
use App\Domain\SiteNotifications\ValueObjects\StoredSiteNotice;
use App\Domain\ValueObjects\UserId;
use DateTimeInterface;

/**
 * The rows of each person's notification centre.
 */
interface SiteNotificationRepositoryInterface
{
    /**
     * Store the notice. With a group key, an unread row of the person with the same key takes it
     * instead: one more notice, its actor counted, and the newest notice's texts and link.
     */
    public function deliver(UserId $userId, SiteNotice $notice): void;

    public function unreadCount(UserId $userId): int;

    /** Visible rows of the person; only the unread ones with $onlyUnread (core#671). */
    public function count(UserId $userId, bool $onlyUnread = false): int;

    /**
     * @return list<StoredSiteNotice> Newest first, by their latest notice.
     */
    public function latest(UserId $userId, int $limit, int $offset, bool $onlyUnread = false): array;

    /**
     * Mark the person's row as read (once: a read row keeps its first read instant).
     */
    public function markRead(UserId $userId, string $id): ?StoredSiteNotice;

    /**
     * Mark the person's visible row as unread again; false when it is not theirs or is hidden.
     */
    public function markUnread(UserId $userId, string $id): bool;

    /**
     * Hide the person's row from their notification centre (core#670), marking it read too so the
     * daily clean-up deletes it with the read ones; false when it is not theirs. Idempotent.
     */
    public function hide(UserId $userId, string $id): bool;

    public function markAllRead(UserId $userId): int;

    public function deleteForUser(UserId $userId): void;

    public function deleteForSubject(string $type, string $id): void;

    /**
     * Mark as read, whoever they belong to, the unread rows of the category about the thing; how
     * many rows changed. Rows already read keep their first read instant.
     */
    public function markReadForSubject(string $category, string $type, string $id): int;

    /**
     * Delete every row, whoever it belongs to, that counts the account among its actors.
     */
    public function deleteForActor(string $actorId): void;

    /**
     * Delete rows read before $readBefore, and unread rows whose latest notice is older than
     * $unreadBefore.
     */
    public function prune(DateTimeInterface $readBefore, DateTimeInterface $unreadBefore): int;
}
