<?php

declare(strict_types=1);

namespace App\Application\Surveys\Contracts;

use App\Application\Surveys\DTOs\SurveySubjectLinksDTO;

/**
 * Links from a subject's page (a table, a tournament, an event…) to its surveys, asked by the
 * module that offers the subject. The surveys module implements it; without it the core's
 * default answers with no links, so the page shows nothing new.
 */
interface SurveySubjectLinksInterface
{
    /**
     * What this person can reach from the subject's page: the page that manages its surveys
     * when they manage the subject (asked live to the subject's source), and the surveys they
     * can answer or see the results of. A visitor (null user) gets no links.
     */
    public function forSubject(string $sourceType, string $subjectId, ?string $userId): SurveySubjectLinksDTO;
}
