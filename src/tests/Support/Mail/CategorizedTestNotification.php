<?php

declare(strict_types=1);

namespace Tests\Support\Mail;

use App\Application\Mail\Contracts\HasMailCategory;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A mail notification of the given category; `$sendable` plays a module's global switch.
 */
final class CategorizedTestNotification extends Notification implements HasMailCategory
{
    public function __construct(
        private readonly string $category,
        private readonly bool $sendable = true,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->sendable;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Test')->line('Test');
    }

    public function mailCategory(): string
    {
        return $this->category;
    }
}
