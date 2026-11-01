<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Application\Surveys\Contracts\SubjectEndSurveyChoiceInterface;
use App\Application\Surveys\DTOs\EndSurveyChoiceDTO;
use App\Application\Surveys\DTOs\EndSurveyOptionDTO;
use App\Application\Surveys\DTOs\EndSurveySelection;
use App\Application\Surveys\Enums\EndSurveyChoiceOutcome;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * "Survey when it ends" for a subject's form (a table, a tournament, an event…). The module that
 * owns the form only adds it; what it offers and where the choice is kept belong to the surveys
 * module, through SubjectEndSurveyChoiceInterface. Without that module it stays hidden.
 *
 * It saves itself once the record exists (on create, right after the record is created), so the
 * module's pages need no hook. A choice that cannot be saved never stops the subject from saving.
 */
final class EndSurveyField
{
    public const string FIELD = 'end_survey';

    public const string BASE_FIELD = 'end_survey_base';

    /**
     * @param  string|Closure  $sourceType  The subject type, or a closure returning it that reads the form (Get, record…)
     */
    public static function forSubject(string|Closure $sourceType): Group
    {
        $lookup = new EndSurveyChoiceLookup($sourceType);

        return Group::make([
            Select::make(self::FIELD)
                ->label(__('filament.end_survey.label'))
                ->native(false)
                ->live()
                ->dehydrated(false)
                ->options(static fn (Select $component): array => self::options($lookup->for($component)->options ?? []))
                ->default(static fn (Select $component): ?string => $lookup->for($component)?->selected)
                ->loadStateFromRelationshipsUsing(static function (Select $component) use ($lookup): void {
                    $component->state($lookup->for($component)?->selected);
                })
                ->visible(static fn (Select $component): bool => $lookup->for($component) instanceof EndSurveyChoiceDTO)
                ->disabled(static fn (Select $component): bool => $lookup->for($component)?->locked === true)
                ->required(static fn (Select $component): bool => $lookup->for($component)?->locked === false)
                ->helperText(static fn (Select $component): string => $lookup->for($component)->lockedReason
                    ?? __('filament.end_survey.helper'))
                ->hintAction(
                    Action::make('editEndSurvey')
                        ->label(__('filament.end_survey.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->url(static fn (Select $component): ?string => $lookup->for($component)?->editUrl)
                        ->visible(static fn (Select $component): bool => $lookup->for($component)?->editUrl !== null),
                )
                ->saveRelationshipsUsing(static function (Select $component, Model $record, Get $get) use ($lookup): void {
                    self::save($lookup, $component, $record, $get);
                }),
            Select::make(self::BASE_FIELD)
                ->label(__('filament.end_survey.base'))
                ->native(false)
                ->dehydrated(false)
                ->options(static fn (Select $component): array => self::options($lookup->for($component)->baseTemplates ?? []))
                ->default(static fn (Select $component): ?string => self::firstBaseTemplate($lookup, $component))
                // A form being edited is not filled with defaults
                ->afterStateHydrated(static function (Select $component, ?string $state) use ($lookup): void {
                    if ($state === null) {
                        $component->state(self::firstBaseTemplate($lookup, $component));
                    }
                })
                ->visible(static function (Select $component, Get $get) use ($lookup): bool {
                    $choice = $lookup->for($component);

                    return $choice instanceof EndSurveyChoiceDTO && $choice->ownValue !== null && $get(self::FIELD) === $choice->ownValue;
                })
                ->disabled(static fn (Select $component): bool => $lookup->for($component)?->locked === true)
                ->required(),
        ]);
    }

    /**
     * Saves only a choice that is open and changed: a locked field runs this too, and saving an
     * untouched default could refuse someone who never meant to choose.
     */
    private static function save(EndSurveyChoiceLookup $lookup, Select $component, Model $record, Get $get): void
    {
        $choice = $lookup->for($component);
        $userId = Auth::id();
        $value = $component->getState();
        if (! $choice instanceof EndSurveyChoiceDTO || $choice->locked || $userId === null || ! is_string($value) || $value === '') {
            return;
        }

        $base = $value === $choice->ownValue ? $get(self::BASE_FIELD) : null;
        if ($value === $choice->selected && $base === null) {
            return;
        }

        try {
            $outcome = app(SubjectEndSurveyChoiceInterface::class)->choose(
                $lookup->sourceType($component),
                (string) $record->getKey(),
                new EndSurveySelection($value, is_string($base) && $base !== '' ? $base : null),
                (string) $userId,
            );
        } catch (AuthorizationException) {
            Notification::make()->warning()->title(__('filament.end_survey.not_allowed'))->send();

            return;
        } finally {
            $lookup->forget();
        }

        match ($outcome) {
            EndSurveyChoiceOutcome::ReleasedAsDraft => Notification::make()->warning()->title(__('filament.end_survey.released_as_draft'))->send(),
            EndSurveyChoiceOutcome::Locked => Notification::make()->warning()->title(__('filament.end_survey.locked'))->send(),
            EndSurveyChoiceOutcome::Saved, EndSurveyChoiceOutcome::Unchanged => null,
        };
    }

    private static function firstBaseTemplate(EndSurveyChoiceLookup $lookup, Select $component): ?string
    {
        return ($lookup->for($component)->baseTemplates[0] ?? null)?->value;
    }

    /**
     * Select options, with grouped ones under their heading.
     *
     * @param  list<EndSurveyOptionDTO>  $options
     * @return array<string, string|array<string, string>>
     */
    private static function options(array $options): array
    {
        $grouped = [];
        foreach ($options as $option) {
            if ($option->group === null) {
                $grouped[$option->value] = $option->label;

                continue;
            }

            $group = $grouped[$option->group] ?? [];
            $grouped[$option->group] = (is_array($group) ? $group : []) + [$option->value => $option->label];
        }

        return $grouped;
    }

    /**
     * The record the field belongs to, when it already exists.
     */
    public static function subjectId(Component $component): ?string
    {
        $record = $component->getRecord();

        return $record instanceof Model && $record->exists ? (string) $record->getKey() : null;
    }
}
