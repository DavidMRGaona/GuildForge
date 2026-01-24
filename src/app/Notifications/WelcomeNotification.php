<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $appName,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $notifiable */
        $loginUrl = route('login');

        return (new MailMessage)
            ->subject(__('auth.welcome_subject', ['app' => $this->appName]))
            ->greeting(__('auth.welcome_greeting', ['name' => $notifiable->name]))
            ->line(__('auth.welcome_line1', ['app' => $this->appName]))
            ->line(__('auth.welcome_line2'))
            ->action(__('auth.welcome_action'), $loginUrl)
            ->line(__('auth.welcome_line3', ['app' => $this->appName]));
    }
}
