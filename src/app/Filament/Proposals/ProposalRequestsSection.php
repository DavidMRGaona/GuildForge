<?php

declare(strict_types=1);

namespace App\Filament\Proposals;

use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Application\Proposals\DTOs\StoredProposalRequestDTO;
use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Application\Proposals\Services\ProposalRequestValueRule;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Proposals\Enums\ProposalRequestFieldType;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\InvalidProposalDataException;
use App\Filament\Proposals\Contracts\ReviewsProposalRequestInPanel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * «Peticiones de módulos» on the record of an event proposal (core#581): what its author asked
 * the modules for, with the values editable and an «Aplicar» toggle each, while it awaits review
 * and only for whoever may review it. A request whose module is no longer active can only be
 * unticked. Saving the record saves the decisions through the requests service, which checks
 * the values again; approving applies the ticked ones.
 *
 * State lives under `module_requests.<slot>` (a key holds dots, which a state path cannot), out of
 * the record's attributes: EditEvent fills it and hands it to the service after saving.
 */
final class ProposalRequestsSection
{
    public const string STATE = 'module_requests';

    public static function make(): Section
    {
        return Section::make(__('filament.proposals.requests.heading'))
            ->description(__('filament.proposals.requests.description'))
            ->icon('heroicon-o-puzzle-piece')
            ->schema(fn (?Model $record): array => self::components($record))
            ->visible(fn (?Model $record): bool => $record instanceof EventModel
                && $record->proposalStatus() instanceof ProposalStatus
                && self::stored($record) !== [])
            ->dehydrated(false)
            ->columnSpanFull();
    }

    /**
     * The form state of the record's requests, for EditEvent to fill.
     *
     * @return array<string, array{apply: bool, values: array<string, bool|int|string|null>}>
     */
    public static function state(Model $record): array
    {
        $state = [];

        foreach (self::stored($record) as $request) {
            $state[self::slot($request->key)] = ['apply' => $request->apply, 'values' => $request->valuesInForce()];
        }

        return $state;
    }

    /**
     * Saves what the reviewer decided, when they may decide: the event awaits review and they hold
     * its review permission. A value the request refuses stops the save (and the approval that
     * saves first) with a message on the section.
     *
     * @param  array<array-key, mixed>  $data  the page's raw form data
     */
    public static function save(Model $record, array $data): void
    {
        if (! self::isEditable($record)) {
            return;
        }

        $state = $data[self::STATE] ?? null;

        if (! is_array($state)) {
            return;
        }

        $decisions = [];

        foreach (self::stored($record) as $request) {
            $decision = $state[self::slot($request->key)] ?? null;

            if (is_array($decision)) {
                $decisions[$request->key] = $decision;
            }
        }

        try {
            app(EventProposalRequestServiceInterface::class)->review((string) auth()->id(), (string) $record->getKey(), $decisions);
        } catch (InvalidProposalDataException) {
            throw ValidationException::withMessages(['data.'.self::STATE => __('proposals.errors.invalid_requests')]);
        }
    }

    /**
     * A state path segment for a key: "." and "-" never appear twice in a row in a key, so
     * replacing them with "__" and "_" keeps keys apart.
     */
    public static function slot(string $key): string
    {
        return 'r_'.str_replace(['.', '-'], ['__', '_'], $key);
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }

    private static function isEditable(?Model $record): bool
    {
        return $record instanceof EventModel
            && $record->proposalStatus() === ProposalStatus::PendingReview
            && ProposalReviewPanel::canReview(ProposalType::Event);
    }

    /**
     * @return list<StoredProposalRequestDTO>
     */
    private static function stored(?Model $record): array
    {
        if (! $record instanceof EventModel) {
            return [];
        }

        return app(EventProposalRequestServiceInterface::class)->forEvent((string) $record->getKey());
    }

    /**
     * @return list<Component>
     */
    private static function components(?Model $record): array
    {
        $editable = self::isEditable($record);
        $components = [];

        foreach (self::stored($record) as $request) {
            $components[] = Fieldset::make($request->label)
                ->statePath(self::STATE.'.'.self::slot($request->key))
                ->schema([
                    Toggle::make('apply')
                        ->label(__('filament.proposals.requests.apply'))
                        ->helperText($request->active ? null : self::text('filament.proposals.requests.inactive'))
                        ->columnSpanFull(),
                    ...self::status($request),
                    ...($request->active
                        ? [Group::make(self::fieldComponents($request))->statePath('values')->columns(2)->columnSpanFull()]
                        : []),
                ])
                ->disabled(! $editable || $request->appliedAt !== null)
                ->columns(2);
        }

        return $components;
    }

    /**
     * @return list<Component>
     */
    private static function status(StoredProposalRequestDTO $request): array
    {
        if ($request->appliedAt instanceof \DateTimeImmutable) {
            return [Text::make(__('filament.proposals.requests.applied_at', [
                'date' => app(TimezoneServiceInterface::class)->format($request->appliedAt, 'd/m/Y H:i'),
            ]))->columnSpanFull()];
        }

        $lines = $request->description !== null ? [Text::make($request->description)->color('gray')->columnSpanFull()] : [];

        // The module's early warnings for the values in force, as the author saw them (core#612)
        foreach ($request->warnings as $warning) {
            $lines[] = Text::make(__('filament.proposals.requests.warning', ['text' => $warning]))->color('warning')->columnSpanFull();
        }

        return $lines;
    }

    /**
     * The module's own components when it brings them, otherwise one per field.
     *
     * @return list<Component>
     */
    private static function fieldComponents(StoredProposalRequestDTO $request): array
    {
        $module = app(EventProposalRequestRegistryInterface::class)->request($request->key);

        if ($module instanceof ReviewsProposalRequestInPanel) {
            try {
                return $module->reviewComponents();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $controllers = array_filter(array_map(static fn (ProposalRequestFieldDTO $field): ?string => $field->visibleWhenField, $request->fields));

        return array_map(
            static fn (ProposalRequestFieldDTO $field): Field => self::field($field, $request->fields, in_array($field->name, $controllers, true)),
            $request->fields,
        );
    }

    /**
     * @param  list<ProposalRequestFieldDTO>  $fields  every field of the request, for dependencies
     */
    private static function field(ProposalRequestFieldDTO $field, array $fields, bool $controlsOthers): Field
    {
        $component = match ($field->type) {
            ProposalRequestFieldType::Boolean => Toggle::make($field->name),
            ProposalRequestFieldType::Number => TextInput::make($field->name)
                ->integer()
                ->minValue($field->min)
                ->maxValue($field->max),
            ProposalRequestFieldType::Select => Select::make($field->name)
                ->options($field->options)
                ->native(false),
            ProposalRequestFieldType::Text => TextInput::make($field->name)
                ->maxLength((int) $field->maxLength),
        };

        $component->label($field->label)
            ->helperText($field->helperText)
            ->required($field->required);

        if ($controlsOthers) {
            $component->live();
        }

        if ($field->visibleWhenField !== null) {
            $component->visible(static function (Get $get) use ($field, $fields): bool {
                $values = [];

                foreach ($fields as $each) {
                    $values[$each->name] = $get($each->name);
                }

                return in_array($field->name, app(ProposalRequestValueRule::class)->visibleFields($fields, $values), true);
            });
        }

        return $component;
    }
}
