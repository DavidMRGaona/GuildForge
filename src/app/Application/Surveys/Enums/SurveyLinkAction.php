<?php

declare(strict_types=1);

namespace App\Application\Surveys\Enums;

/**
 * What a survey link lets a participant do from the subject's page.
 */
enum SurveyLinkAction: string
{
    /** The survey is open and the person has not answered it yet. */
    case Respond = 'respond';

    /** The survey shows its results to whoever answered it. */
    case ViewResults = 'view_results';
}
