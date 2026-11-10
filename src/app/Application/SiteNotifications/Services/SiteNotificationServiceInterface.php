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

    /** Every row of the person. */
    public function total(object $user): int;

    /**
     * Newest first (by its latest notice).
     *
     * @return list<SiteNotificationItemDTO>
     */
    public function latest(object $user, int $limit, int $offset = 0): array;

    /**
     * Mark the person's notice as read and say where it leads, or null when it is not theirs.
     */
    public function open(object $user, string $id): ?string;

    /** Mark every notice of the person as read; how many were unread. */
    public function markAllRead(object $user): int;

    /**
     * Forget the notices about a thing that no longer exists, for everybody. Called together with
     * MailSubjectMuteServiceInterface::forgetSubject(), so modules make one call when they delete it.
     */
    public function forgetSubject(string $type, string $id): void;

    /**
     * Delete read notices older than $readDays and unread ones older than $unreadDays (by their
     * latest notice); how many rows went.
     */
    public function prune(int $readDays, int $unreadDays): int;
}
