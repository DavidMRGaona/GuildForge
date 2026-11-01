<?php

declare(strict_types=1);

namespace App\Infrastructure\Surveys\Services;

use App\Application\Surveys\Contracts\SubjectEndSurveyChoiceInterface;
use App\Application\Surveys\DTOs\EndSurveyChoiceDTO;
use App\Application\Surveys\DTOs\EndSurveySelection;
use App\Application\Surveys\Enums\EndSurveyChoiceOutcome;
use App\Application\Surveys\Enums\SurveySurface;

/**
 * No surveys module: a subject's form has no end survey to choose.
 */
final readonly class NullSubjectEndSurveyChoice implements SubjectEndSurveyChoiceInterface
{
    public function forForm(string $sourceType, ?string $subjectId, string $userId, SurveySurface $surface): ?EndSurveyChoiceDTO
    {
        return null;
    }

    public function choose(string $sourceType, string $subjectId, EndSurveySelection $selection, string $userId): EndSurveyChoiceOutcome
    {
        return EndSurveyChoiceOutcome::Unchanged;
    }
}
