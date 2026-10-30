<?php

declare(strict_types=1);

namespace Tests\Support\Surveys;

use App\Application\Surveys\Contracts\SurveySubjectSourceInterface;
use App\Application\Surveys\DTOs\SurveyAudienceDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;

/**
 * Survey subject source used in tests to simulate a module offering surveyable subjects.
 */
class FakeSurveySubjectSource implements SurveySubjectSourceInterface
{
    public function sourceType(): string
    {
        return 'fake';
    }

    public function sourceLabel(): string
    {
        return 'Fake source';
    }

    public function find(string $subjectId): ?SurveySubjectDTO
    {
        return null;
    }

    public function audience(string $subjectId): SurveyAudienceDTO
    {
        return SurveyAudienceDTO::empty();
    }

    public function canManage(string $userId, string $subjectId): bool
    {
        return false;
    }
}
