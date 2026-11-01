<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Application\Surveys\Contracts\SubjectEndSurveyChoiceInterface;
use App\Application\Surveys\DTOs\EndSurveyChoiceDTO;
use App\Application\Surveys\DTOs\EndSurveyOptionDTO;
use App\Application\Surveys\DTOs\EndSurveySelection;
use App\Application\Surveys\Enums\EndSurveyChoiceOutcome;
use App\Application\Surveys\Enums\SurveySurface;
use Tests\TestCase;

final class SubjectEndSurveyChoiceBindingTest extends TestCase
{
    public function test_without_a_surveys_module_there_is_nothing_to_choose(): void
    {
        $choice = $this->app->make(SubjectEndSurveyChoiceInterface::class);

        $this->assertNull($choice->forForm('example-thing', 'subject-1', 'user-1', SurveySurface::Panel));
        $this->assertNull($choice->forForm('example-thing', null, 'user-1', SurveySurface::Web));
        $this->assertSame(
            EndSurveyChoiceOutcome::Unchanged,
            $choice->choose('example-thing', 'subject-1', new EndSurveySelection('none'), 'user-1'),
        );
    }

    public function test_the_default_is_shared(): void
    {
        $this->assertSame(
            $this->app->make(SubjectEndSurveyChoiceInterface::class),
            $this->app->make(SubjectEndSurveyChoiceInterface::class),
        );
    }

    public function test_a_module_binding_replaces_the_default(): void
    {
        $moduleChoice = new class implements SubjectEndSurveyChoiceInterface
        {
            public function forForm(string $sourceType, ?string $subjectId, string $userId, SurveySurface $surface): EndSurveyChoiceDTO
            {
                return new EndSurveyChoiceDTO([new EndSurveyOptionDTO('none', 'Ninguna')], 'none');
            }

            public function choose(string $sourceType, string $subjectId, EndSurveySelection $selection, string $userId): EndSurveyChoiceOutcome
            {
                return EndSurveyChoiceOutcome::Saved;
            }
        };

        $this->app->singleton(SubjectEndSurveyChoiceInterface::class, fn (): SubjectEndSurveyChoiceInterface => $moduleChoice);

        $this->assertSame('none', $this->app->make(SubjectEndSurveyChoiceInterface::class)->forForm('example-thing', null, 'user-1', SurveySurface::Panel)?->selected);
    }
}
