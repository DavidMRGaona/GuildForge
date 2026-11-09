<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Application\Mail\Contracts\IsMailDigest;
use App\Notifications\Support\EscapesMarkdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One person's daily digest (core#333): the notices of the categories they chose to receive
 * once a day, grouped by category. Sent right away by MailDigestSender (never queued), so the
 * notices are only deleted once it went out.
 */
final class MailDigestNotification extends Notification implements IsMailDigest
{
    /**
     * @param  list<array{key: string, label: string, lines: list<array{time: string, text: string, url: string|null}>}>  $sections
     * @param  int  $total  Every notice of the digest, including those beyond the lines shown.
     */
    public function __construct(
        private readonly array $sections,
        private readonly int $total,
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
        $message = (new MailMessage)
            ->subject(trans_choice('mail.digest.subject', $this->total, ['count' => $this->total]))
            ->line(__('mail.digest.intro'));

        $shown = 0;
        foreach ($this->sections as $section) {
            $message->line('**'.EscapesMarkdown::escape($section['label']).'**');

            foreach ($section['lines'] as $line) {
                $shown++;
                $text = $line['time'].' · '.EscapesMarkdown::escape($line['text']);
                $message->line($line['url'] === null
                    ? $text
                    : $text.' · ['.__('mail.digest.open').']('.$this->markdownUrl($line['url']).')');
            }
        }

        $hidden = $this->total - $shown;
        if ($hidden > 0) {
            $message->line(trans_choice('mail.digest.more', $hidden, ['count' => $hidden]));
        }

        return $message;
    }

    public function digestCategories(): array
    {
        return array_map(static fn (array $section): string => $section['key'], $this->sections);
    }

    /**
     * A link destination ends at the first space or unbalanced parenthesis.
     */
    private function markdownUrl(string $url): string
    {
        return str_replace([' ', '(', ')', '<', '>'], ['%20', '%28', '%29', '%3C', '%3E'], $url);
    }
}
