<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteNotifications;

use App\Application\Services\TimezoneServiceInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationItemDTO;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationRepositoryInterface;
use App\Domain\SiteNotifications\ValueObjects\StoredSiteNotice;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;

final readonly class SiteNotificationService implements SiteNotificationServiceInterface
{
    public function __construct(
        private SiteNotificationRepositoryInterface $notices,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function unreadCount(object $user): int
    {
        $userId = $this->userId($user);

        return $userId instanceof UserId ? $this->notices->unreadCount($userId) : 0;
    }

    public function total(object $user): int
    {
        $userId = $this->userId($user);

        return $userId instanceof UserId ? $this->notices->count($userId) : 0;
    }

    public function latest(object $user, int $limit, int $offset = 0): array
    {
        $userId = $this->userId($user);

        if (! $userId instanceof UserId) {
            return [];
        }

        return array_map(fn (StoredSiteNotice $notice): SiteNotificationItemDTO => new SiteNotificationItemDTO(
            id: $notice->id,
            category: $notice->category,
            title: $notice->title,
            body: $notice->body,
            url: $notice->url,
            icon: $notice->icon,
            count: $notice->count,
            unread: $notice->isUnread(),
            notifiedAt: $this->timezone->iso($notice->notifiedAt),
        ), $this->notices->latest($userId, $limit, $offset));
    }

    public function open(object $user, string $id): ?string
    {
        $userId = $this->userId($user);

        if (! $userId instanceof UserId || ! Str::isUuid($id)) {
            return null;
        }

        $notice = $this->notices->markRead($userId, $id);

        if (! $notice instanceof StoredSiteNotice) {
            return null;
        }

        // Checked when it was stored; checked again before it becomes a redirect
        return SiteNotificationDTO::isSitePath($notice->url) ? $notice->url : '/';
    }

    public function markRead(object $user, string $id): bool
    {
        $userId = $this->userId($user);

        return $userId instanceof UserId && Str::isUuid($id) && $this->notices->markRead($userId, $id) instanceof StoredSiteNotice;
    }

    public function markUnread(object $user, string $id): bool
    {
        $userId = $this->userId($user);

        return $userId instanceof UserId && Str::isUuid($id) && $this->notices->markUnread($userId, $id);
    }

    public function hide(object $user, string $id): bool
    {
        $userId = $this->userId($user);

        return $userId instanceof UserId && Str::isUuid($id) && $this->notices->hide($userId, $id);
    }

    public function markAllRead(object $user): int
    {
        $userId = $this->userId($user);

        return $userId instanceof UserId ? $this->notices->markAllRead($userId) : 0;
    }

    public function resolveSubject(string $category, string $type, string $id): int
    {
        return $this->notices->markReadForSubject($category, $type, $id);
    }

    public function forgetSubject(string $type, string $id): void
    {
        $this->notices->deleteForSubject($type, $id);
    }

    public function forgetActor(string $actorId): void
    {
        $this->notices->deleteForActor($actorId);
    }

    public function prune(int $readDays, int $unreadDays): int
    {
        return $this->notices->prune(now()->subDays($readDays), now()->subDays($unreadDays));
    }

    private function userId(object $user): ?UserId
    {
        return $user instanceof UserModel ? UserId::fromString($user->id) : null;
    }
}
