<?php

declare(strict_types=1);

namespace App\Infrastructure\Surveys\Services;

use App\Application\Surveys\Contracts\SurveySubjectLinksInterface;
use App\Application\Surveys\DTOs\SurveySubjectLinksDTO;

/**
 * No surveys module: a subject's page has no survey links.
 */
final readonly class NullSurveySubjectLinks implements SurveySubjectLinksInterface
{
    public function forSubject(string $sourceType, string $subjectId, ?string $userId): SurveySubjectLinksDTO
    {
        return SurveySubjectLinksDTO::none();
    }
}
