<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteNotifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationGroupDTO;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationRepositoryInterface;
use App\Domain\SiteNotifications\ValueObjects\SiteNotice;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Mail\Preferences\NotificationMailSubject;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The «site» notification channel (HasSiteNotification::CHANNEL): stores a notice in the
 * person's notification centre, when its category offers site notices and the person wants it.
 *
 * Everything is decided here, when the notice is delivered (inside the queue worker for a queued
 * notification), so a choice made or an access lost while it waited counts. Bound scoped, so a
 * request or a job warns once per notification class that cannot be shown.
 */
final class SiteNotificationChannel
{
    /**
     * @var array<class-string, true>
     */
    private array $warned = [];

    public function __construct(
        private readonly MailCategoryRegistryInterface $categories,
        private readonly SiteNotificationPreferenceServiceInterface $preferences,
        private readonly SiteNotificationRepositoryInterface $notices,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        // Only accounts have a notification centre; deactivated and anonymized ones are nobody now
        if (! $notifiable instanceof UserModel || $notifiable->anonymized_at !== null || $notifiable->trashed()) {
            return;
        }

        $category = $this->categories->forNotification($notification);

        if (! $notification instanceof HasSiteNotification || ! $category instanceof MailCategoryDTO || ! $category->site) {
            $this->warnOnce($notification);

            return;
        }

        $subject = NotificationMailSubject::of($notification);

        if (! $this->preferences->receives($notifiable, $category, $subject)) {
            return;
        }

        try {
            $notice = $notification->toSiteNotification($notifiable);
        } catch (Throwable $exception) {
            Log::warning('A site notification could not be built; it is not shown.', [
                'notification' => $notification::class,
                'user_id' => $notifiable->id,
                'exception' => $exception,
            ]);

            return;
        }

        // The module found the person should not get it (they can no longer see what it is about)
        if (! $notice instanceof SiteNotificationDTO) {
            return;
        }

        // Nobody is told about what they did themselves
        if ($notice->actorId !== null && $notice->actorId === (string) $notifiable->id) {
            return;
        }

        $this->notices->deliver(UserId::fromString($notifiable->id), $this->toNotice($category, $notice, $subject));
    }

    private function toNotice(MailCategoryDTO $category, SiteNotificationDTO $notice, ?MailSubjectDTO $subject): SiteNotice
    {
        return new SiteNotice(
            category: $category->key,
            title: $notice->title,
            url: $notice->url,
            body: $notice->body,
            icon: $notice->icon,
            groupKey: $notice->groupKey,
            actorId: $notice->actorId,
            subjectType: $subject?->type,
            subjectId: $subject?->id,
            groupedTitle: static fn (int $count, int $otherActors): string => $notice->titleFor(
                new SiteNotificationGroupDTO(count: $count, otherActors: $otherActors),
            ),
        );
    }

    private function warnOnce(Notification $notification): void
    {
        if (isset($this->warned[$notification::class])) {
            return;
        }

        $this->warned[$notification::class] = true;

        Log::warning('A site notification without a mail category that offers site notices was sent; it is not shown.', [
            'notification' => $notification::class,
            'category' => $notification instanceof HasMailCategory ? $notification->mailCategory() : null,
        ]);
    }
}
