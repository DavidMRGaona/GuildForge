<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Support\EscapesMarkdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that their roles that require association membership are paused.
 */
final class InactiveRolesNotification extends Notification implements ShouldQueue
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('authorization.inactive_roles.person_subject', ['site' => $this->siteName]))
            ->line(__('authorization.inactive_roles.person_body', ['roles' => EscapesMarkdown::escape($this->roles)]));
    }
}
