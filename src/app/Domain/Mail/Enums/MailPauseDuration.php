<?php

declare(strict_types=1);

namespace App\Domain\Mail\Enums;

/**
 * How long a person pauses their optional mail.
 */
enum MailPauseDuration: string
{
    case Week = 'week';
    case Month = 'month';
    /** Until the person resumes by hand. */
    case Indefinite = 'indefinite';
}
