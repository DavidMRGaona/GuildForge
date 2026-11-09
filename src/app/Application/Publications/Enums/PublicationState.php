<?php

declare(strict_types=1);

namespace App\Application\Publications\Enums;

/**
 * Where a published record stands when its source is asked: live, never a snapshot.
 */
enum PublicationState: string
{
    /** Published and not over yet (scheduled, recruiting, in progress…). */
    case Active = 'active';

    /** It will not take place: the record stays published so that whoever announced it can say so. */
    case Cancelled = 'cancelled';

    /** It is over (completed, or its end has passed). */
    case Ended = 'ended';
}
