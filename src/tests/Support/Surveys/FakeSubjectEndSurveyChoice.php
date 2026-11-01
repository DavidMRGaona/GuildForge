<?php

declare(strict_types=1);

namespace Tests\Support\Surveys;

use App\Application\Surveys\Contracts\SubjectEndSurveyChoiceInterface;
use App\Application\Surveys\DTOs\EndSurveyChoiceDTO;
use App\Application\Surveys\DTOs\EndSurveySelection;
use App\Application\Surveys\Enums\EndSurveyChoiceOutcome;
use App\Application\Surveys\Enums\SurveySurface;
use Throwable;

/**
 * End survey choice used in tests to simulate the surveys module: it offers a fixed choice and
 * records what the form asks and saves.
 */
final class FakeSubjectEndSurveyChoice implements SubjectEndSurveyChoiceInterface
{
    /** @var list<array{sourceType: string, subjectId: string|null, userId: string}> */
    public array $asked = [];

    /** @var list<array{sourceType: string, subjectId: string, selection: EndSurveySelection, userId: string}> */
    public array $chosen = [];

    public function __construct(
        public ?EndSurveyChoiceDTO $choice,
        public EndSurveyChoiceOutcome $outcome = EndSurveyChoiceOutcome::Saved,
        public ?Throwable $failure = null,
    ) {}

    public function forForm(string $sourceType, ?string $subjectId, string $userId, SurveySurface $surface): ?EndSurveyChoiceDTO
    {
        $this->asked[] = ['sourceType' => $sourceType, 'subjectId' => $subjectId, 'userId' => $userId];

        return $this->choice;
    }

    public function choose(string $sourceType, string $subjectId, EndSurveySelection $selection, string $userId): EndSurveyChoiceOutcome
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        $this->chosen[] = ['sourceType' => $sourceType, 'subjectId' => $subjectId, 'selection' => $selection, 'userId' => $userId];

        return $this->outcome;
    }
}
