<?php

declare(strict_types=1);

namespace App\Application\Surveys\Contracts;

use App\Application\Surveys\DTOs\EndSurveyChoiceDTO;
use App\Application\Surveys\DTOs\EndSurveySelection;
use App\Application\Surveys\Enums\EndSurveyChoiceOutcome;
use App\Application\Surveys\Enums\SurveySurface;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Which survey goes out when a subject (a table, a tournament, an event…) ends, chosen from the
 * subject's own form. The surveys module implements it and keeps the choice; without it the
 * core's default offers nothing, so the form shows no field.
 */
interface SubjectEndSurveyChoiceInterface
{
    /**
     * What the subject's form offers this person. Null when there is nothing to offer: no
     * surveys module, a type no module offers, or someone who may not choose. A subject being
     * created has no id yet.
     */
    public function forForm(string $sourceType, ?string $subjectId, string $userId, SurveySurface $surface): ?EndSurveyChoiceDTO;

    /**
     * Keeps the choice for the subject, checking live that this person may make it.
     *
     * @throws AuthorizationException
     */
    public function choose(string $sourceType, string $subjectId, EndSurveySelection $selection, string $userId): EndSurveyChoiceOutcome;
}
