<?php

declare(strict_types=1);

namespace Tests\Support\Mail;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * A mail notification of the given category about one subject (a game, a tournament...);
 * `$failing` plays a module whose subject cannot be built.
 */
final class SubjectTestNotification extends Notification implements HasMailCategory, HasMailSubject
{
    public function __construct(
        private readonly string $category,
        private readonly ?MailSubjectDTO $subject,
        private readonly bool $failing = false,
    ) {}

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

    public function mailCategory(): string
    {
        return $this->category;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        if ($this->failing) {
            throw new RuntimeException('The subject is gone');
        }

        return $this->subject;
    }
}
