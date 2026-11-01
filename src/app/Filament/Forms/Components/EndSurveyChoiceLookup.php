<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Application\Surveys\Contracts\SubjectEndSurveyChoiceInterface;
use App\Application\Surveys\DTOs\EndSurveyChoiceDTO;
use App\Application\Surveys\Enums\SurveySurface;
use Closure;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Auth;

/**
 * What the surveys module offers one form, asked once per subject and person: Filament reads
 * options, visibility and state many times per render. Forgotten after saving, so the form
 * shows what the save left. The surveys module is asked when the form is shown, not when a
 * module adds the field at boot.
 *
 * @internal used by EndSurveyField
 */
final class EndSurveyChoiceLookup
{
    /** @var array<string, EndSurveyChoiceDTO|null> */
    private array $choices = [];

    public function __construct(
        private readonly string|Closure $sourceType,
    ) {}

    public function for(Component $component): ?EndSurveyChoiceDTO
    {
        $userId = Auth::id();
        if ($userId === null) {
            return null;
        }

        $sourceType = $this->sourceType($component);
        $subjectId = EndSurveyField::subjectId($component);
        $key = "{$sourceType}|{$subjectId}|{$userId}";
        if (! array_key_exists($key, $this->choices)) {
            $this->choices[$key] = app(SubjectEndSurveyChoiceInterface::class)->forForm($sourceType, $subjectId, (string) $userId, SurveySurface::Panel);
        }

        return $this->choices[$key];
    }

    public function sourceType(Component $component): string
    {
        $sourceType = $component->evaluate($this->sourceType);

        return is_string($sourceType) ? $sourceType : '';
    }

    public function forget(): void
    {
        $this->choices = [];
    }
}
