<?php

declare(strict_types=1);

namespace Tests\Support\Mail;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A mail notification that does not say which category it belongs to.
 */
final class UncategorizedTestNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Test')->line('Test');
    }
}
