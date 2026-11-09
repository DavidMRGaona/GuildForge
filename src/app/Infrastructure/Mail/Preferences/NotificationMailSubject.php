<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads the subject a notification says it is about. A notification that fails to give one is
 * treated as being about nothing: its mail is sent, without the line to mute it.
 */
final class NotificationMailSubject
{
    public static function of(object $notification): ?MailSubjectDTO
    {
        if (! $notification instanceof HasMailSubject) {
            return null;
        }

        try {
            return $notification->mailSubject();
        } catch (Throwable $exception) {
            Log::warning('A mail notification could not say what it is about; it is sent without a subject.', [
                'notification' => $notification::class,
                'exception' => $exception,
            ]);

            return null;
        }
    }
}
