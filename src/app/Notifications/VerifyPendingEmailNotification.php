<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\Contracts\NamesMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

final class VerifyPendingEmailNotification extends Notification implements NamesMailRecipient, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $pendingEmail,
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
        /** @var UserModel $notifiable */
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(__('auth.verify_email_change_subject'))
            ->line(__('auth.verify_email_change_line1'))
            ->action(__('auth.verify_email_change_action'), $verificationUrl)
            ->line(__('auth.verify_email_change_line2'));
    }

    /**
     * Get the verification URL for the pending email.
     */
    protected function verificationUrl(object $notifiable): string
    {
        /** @var UserModel $notifiable */
        return URL::temporarySignedRoute(
            'verification.pending-email',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($this->pendingEmail),
            ]
        );
    }

    /**
     * Send to the pending email, not the current one: the link proves the new address.
     */
    public function mailRecipient(): string
    {
        return $this->pendingEmail;
    }
}
