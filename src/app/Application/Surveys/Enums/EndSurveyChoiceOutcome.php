<?php

declare(strict_types=1);

namespace App\Application\Surveys\Enums;

/**
 * What became of an end survey choice saved from a subject's form.
 */
enum EndSurveyChoiceOutcome: string
{
    /** The choice is stored and will be used when the subject ends. */
    case Saved = 'saved';

    /** It was already the choice in force: nothing changed. */
    case Unchanged = 'unchanged';

    /** Saved, and the end survey prepared before had been edited, so it was kept as a draft. */
    case ReleasedAsDraft = 'released_as_draft';

    /** The end survey already went its way meanwhile, so the choice can no longer change. */
    case Locked = 'locked';
}
