<?php

declare(strict_types=1);

namespace App\Application\Surveys\Enums;

/**
 * Where a subject's form is shown, so links lead to the matching survey editor.
 */
enum SurveySurface: string
{
    /** The admin panel. */
    case Panel = 'panel';

    /** The public site, for organizers without the panel. */
    case Web = 'web';
}
