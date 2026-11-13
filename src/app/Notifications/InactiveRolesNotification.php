<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Notifications\Support\EscapesMarkdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that their roles that require association membership are paused.
 */
final class InactiveRolesNotification extends Notification implements HasMailCategory, HasSiteNotification, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $siteName,
        private readonly string $roles,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('authorization.inactive_roles.person_subject', ['site' => $this->siteName]))
            ->line(__('authorization.inactive_roles.person_body', ['roles' => EscapesMarkdown::escape($this->roles)]));
    }

    public function toSiteNotification(object $notifiable): SiteNotificationDTO
    {
        return new SiteNotificationDTO(
            title: __('authorization.inactive_roles.person_site_title'),
            url: route('profile.show', absolute: false),
            body: __('authorization.inactive_roles.site_roles', ['roles' => $this->roles]),
            icon: 'shield-check',
        );
    }

    public function mailCategory(): string
    {
        return CoreMailCategories::ACCOUNT;
    }
}
