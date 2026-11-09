<?php

declare(strict_types=1);

namespace Tests\Support\Mail;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailDigestLine;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailDigestLineDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * A mail notification that gives its own line for the daily digest (core#333); `$failing`
 * plays a module whose line cannot be built.
 */
final class DigestLineTestNotification extends Notification implements HasMailCategory, HasMailDigestLine, HasMailSubject
{
    public function __construct(
        private readonly string $category,
        private readonly ?MailDigestLineDTO $line,
        private readonly ?MailSubjectDTO $subject = null,
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
        return (new MailMessage)->subject('Test subject')->line('Test')->action('Open', 'https://example.test/fallback');
    }

    public function mailCategory(): string
    {
        return $this->category;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return $this->subject;
    }

    public function mailDigestLine(object $notifiable): ?MailDigestLineDTO
    {
        if ($this->failing) {
            throw new RuntimeException('The line cannot be built');
        }

        return $this->line;
    }
}
