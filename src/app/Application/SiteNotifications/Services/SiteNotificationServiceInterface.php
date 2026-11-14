<?php

declare(strict_types=1);

namespace App\Application\SiteNotifications\Services;

use App\Application\SiteNotifications\DTOs\SiteNotificationItemDTO;

/**
 * Each account's notices in the notification centre (core#491). Anything that is not an account
 * has none: counts are 0, lists empty and nothing is marked.
 *
 * Notices arrive through the «site» channel (HasSiteNotification); never write or read the
 * `site_notifications` table directly.
 */
interface SiteNotificationServiceInterface
{
    /** Unread rows (a group counts once). */
    public function unreadCount(object $user): int;

    /** Every visible row of the person; only the unread ones with $onlyUnread (core#671). */
    public function total(object $user, bool $onlyUnread = false): int;

    /**
     * Newest first (by its latest notice); only the unread ones with $onlyUnread (core#671).
     *
     * @return list<SiteNotificationItemDTO>
     */
    public function latest(object $user, int $limit, int $offset = 0, bool $onlyUnread = false): array;

    /**
     * Mark the person's notice as read and say where it leads, or null when it is not theirs.
     */
    public function open(object $user, string $id): ?string;

    /** Mark the person's notice as read without opening it; false when it is not theirs. */
    public function markRead(object $user, string $id): bool;

    /** Mark the person's visible notice as unread again (core#670); false when it is not theirs. */
    public function markUnread(object $user, string $id): bool;

    /**
     * Hide the person's notice from their notification centre (core#670), for them only; it counts
     * as read and goes with the daily clean-up. False when it is not theirs.
     */
    public function hide(object $user, string $id): bool;

    /** Mark every notice of the person as read; how many were unread. */
    public function markAllRead(object $user): int;

    /**
     * The task a notice asked for is done (core#668): mark as read, for everybody, the notices of
     * the category about the thing (a proposal reviewed by someone else, a report already
     * moderated). By category, so other notices about the same thing (the author's «approved»)
     * stay as they are; read, not deleted, so people still see there was something to do. Call
     * it after the change is saved; how many rows changed.
     */
    public function resolveSubject(string $category, string $type, string $id): int;

    /**
     * Forget the notices about a thing that no longer exists, for everybody. Called together with
     * MailSubjectMuteServiceInterface::forgetSubject(), so modules make one call when they delete it.
     */
    public function forgetSubject(string $type, string $id): void;

    /**
     * Forget every notice that has the account as one of its actors, for everybody: read or unread,
     * single or grouped (a group's title may name the account, and its text cannot be rewritten
     * here). Anonymizing an account calls it, inside the same transaction; idempotent.
     */
    public function forgetActor(string $actorId): void;

    /**
     * Delete read notices older than $readDays and unread ones older than $unreadDays (by their
     * latest notice); how many rows went.
     */
    public function prune(int $readDays, int $unreadDays): int;
}
