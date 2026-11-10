<?php

declare(strict_types=1);

namespace Tests\Support\SiteNotifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * A notification of the given category shown in the notification centre (and, with
 * `$channels`, also mailed). `$notice` null plays a module that finds the person can no longer
 * see what it is about; `$failing` one whose notice cannot be built.
 */
final class SiteTestNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification
{
    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        private readonly string $category,
        private readonly ?SiteNotificationDTO $notice,
        private readonly ?MailSubjectDTO $subject = null,
        private readonly bool $failing = false,
        private readonly array $channels = [HasSiteNotification::CHANNEL],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Test')->line('Test');
    }

    public function mailCategory(): string
    {
        return $this->category;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return $this->subject;
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        if ($this->failing) {
            throw new RuntimeException('The topic is gone');
        }

        return $this->notice;
    }
}
