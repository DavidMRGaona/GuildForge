<?php

declare(strict_types=1);

namespace App\Application\Surveys\Enums;

/**
 * Where a survey subject stands when its source is asked: not a snapshot, so surveys never
 * store it. A survey prepared before the end waits while the subject is active.
 */
enum SurveySubjectStatus: string
{
    /** It has not ended yet (scheduled, in progress…): SurveySubjectEnded is still to come. */
    case Active = 'active';

    /** It ended: SurveySubjectEnded was (or is being) dispatched. */
    case Ended = 'ended';

    /** It will never end: SurveySubjectCancelled was (or is being) dispatched. */
    case Cancelled = 'cancelled';
}
