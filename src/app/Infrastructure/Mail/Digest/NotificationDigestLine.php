<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Digest;

use App\Application\Mail\Contracts\HasMailDigestLine;
use App\Application\Mail\DTOs\MailDigestLineDTO;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * The line a notice takes in the daily digest: its own (HasMailDigestLine) or, when it gives
 * none, the subject of its mail and the address of its button. Every notification has a subject,
 * and the subject already says in one line what the mail is about.
 */
final class NotificationDigestLine
{
    /**
     * @return MailDigestLineDTO|null Null when the notice cannot be written as a line: its mail is
     *                                not a MailMessage (a Mailable may be anything) or has no subject.
     */
    public static function of(object $notification, object $notifiable): ?MailDigestLineDTO
    {
        if ($notification instanceof HasMailDigestLine) {
            try {
                $line = $notification->mailDigestLine($notifiable);

                if ($line instanceof MailDigestLineDTO) {
                    return $line;
                }
            } catch (Throwable $exception) {
                Log::warning('A notification could not give its daily digest line; its mail subject is used.', [
                    'notification' => $notification::class,
                    'exception' => $exception,
                ]);
            }
        }

        return self::fromMail($notification, $notifiable);
    }

    private static function fromMail(object $notification, object $notifiable): ?MailDigestLineDTO
    {
        if (! method_exists($notification, 'toMail')) {
            return null;
        }

        $message = $notification->toMail($notifiable);

        if (! $message instanceof MailMessage || blank($message->subject)) {
            return null;
        }

        try {
            return new MailDigestLineDTO($message->subject, $message->actionUrl);
        } catch (InvalidArgumentException) {
            // A button to a relative or odd address: the line goes without it
            return new MailDigestLineDTO($message->subject);
        }
    }
}
