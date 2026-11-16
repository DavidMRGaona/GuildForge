<?php

declare(strict_types=1);

namespace App\Notifications\TwoFactor;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Notifications\Support\EscapesMarkdown;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A change to the person's two-factor authentication (core#768), always mailed: if it was not
 * them, it is how they learn someone else holds their password. Texts in two_factor.mail.<type>.
 */
abstract class TwoFactorSecurityNotification extends Notification implements HasMailCategory, ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $occurredAt  ISO 8601 instant of the change, shown in the display zone
     */
    public function __construct(
        protected readonly string $siteName,
        protected readonly string $occurredAt,
    ) {}

    /**
     * Key under two_factor.mail: enabled, disabled, reset, recovery_code_used, recovery_codes_regenerated.
     */
    abstract protected function type(): string;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('two_factor.mail.'.$this->type().'.subject', ['site' => $this->siteName]))
            ->line(__('two_factor.mail.'.$this->type().'.body', [
                'site' => EscapesMarkdown::escape($this->siteName),
                'when' => app(TimezoneServiceInterface::class)->format(new DateTimeImmutable($this->occurredAt)),
            ]));

        foreach ($this->extraLines() as $line) {
            $message->line($line);
        }

        return $message
            ->line(__($this->notYouKey()))
            ->action(__('two_factor.mail.action'), route('profile.show'));
    }

    public function mailCategory(): string
    {
        return CoreMailCategories::ACCOUNT_SECURITY;
    }

    /**
     * The closing line about not having done it: the same for every change unless a type needs its own.
     */
    protected function notYouKey(): string
    {
        return 'two_factor.mail.not_you';
    }

    /**
     * @return list<string>
     */
    protected function extraLines(): array
    {
        return [];
    }
}
