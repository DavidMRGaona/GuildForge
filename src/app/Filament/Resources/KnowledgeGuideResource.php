<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Knowledge\Services\GuideOverrideServiceInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Knowledge\Services\KnowledgeMetricsServiceInterface;
use App\Application\Knowledge\Services\SiteGuideServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Actions\RowDetailsAction;
use App\Filament\Forms\RichEditor\KnowledgeActionBlock;
use App\Filament\Resources\KnowledgeGuideResource\Pages\CreateKnowledgeGuide;
use App\Filament\Resources\KnowledgeGuideResource\Pages\EditKnowledgeGuide;
use App\Filament\Resources\KnowledgeGuideResource\Pages\ListKnowledgeGuides;
use App\Filament\Resources\KnowledgeGuideResource\SiteGuideFormData;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeCategoryModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Services\SiteGuideService;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every help guide of the site in one list: the site's own guides are written and deleted here,
 * official ones (core and modules) are only hidden or starred. Rows are the guides in the fallback
 * locale, the one site guides are written in.
 */
class KnowledgeGuideResource extends BaseResource
{
    /** Days of votes shown in the list */
    public const int METRICS_DAYS = 30;

    protected static ?string $model = KnowledgeGuideModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'guias-de-ayuda';

    public static function getNavigationGroup(): ?string
    {
        return 'Contenido';
    }

    public static function getModelLabel(): string
    {
        return __('filament.knowledge_guides.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.knowledge_guides.plural');
    }

    /**
     * @return Builder<KnowledgeGuideModel>
     */
    public static function getEloquentQuery(): Builder
    {
        return KnowledgeGuideModel::query()
            ->with('override')
            ->where('locale', self::locale())
            ->where(static function (Builder $query): void {
                // Drafts of the site's guides are listed; inactive official guides are gone from the files
                $query->where('source', GuideSource::Site->value)->orWhere('active', true);
            });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.knowledge_guides.sections.guide'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament.knowledge_guides.fields.title'))
                            ->required()
                            ->maxLength(SiteGuideService::TITLE_MAX),
                        Textarea::make('summary')
                            ->label(__('filament.knowledge_guides.fields.summary'))
                            ->helperText(__('filament.knowledge_guides.fields.summary_help'))
                            ->required()
                            ->maxLength(SiteGuideService::SUMMARY_MAX)
                            ->rows(2),
                        Grid::make(3)
                            ->schema([
                                Select::make('audience')
                                    ->label(__('filament.knowledge_guides.fields.audience'))
                                    ->options(self::audienceOptions())
                                    ->default(GuideAudience::Site->value)
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set): mixed => $set('category_id', null)),
                                Select::make('category_id')
                                    ->label(__('filament.knowledge_guides.fields.category'))
                                    ->options(fn (Get $get): array => self::categoryOptions($get('audience')))
                                    ->required()
                                    ->native(false),
                                TextInput::make('sort_order')
                                    ->label(__('filament.knowledge_guides.fields.order'))
                                    ->helperText(__('filament.knowledge_guides.fields.order_help'))
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(9999)
                                    ->default(100)
                                    ->required(),
                            ]),
                        TagsInput::make('keywords')
                            ->label(__('filament.knowledge_guides.fields.keywords'))
                            ->helperText(__('filament.knowledge_guides.fields.keywords_help'))
                            ->nestedRecursiveRules(['string', 'max:60']),
                        Grid::make(2)
                            ->schema([
                                Toggle::make('active')
                                    ->label(__('filament.knowledge_guides.fields.published'))
                                    ->helperText(__('filament.knowledge_guides.fields.published_help'))
                                    ->default(true),
                                Toggle::make('featured')
                                    ->label(__('filament.knowledge_guides.fields.featured'))
                                    ->helperText(__('filament.knowledge_guides.fields.featured_help')),
                            ]),
                    ]),
                Section::make(__('filament.knowledge_guides.sections.body'))
                    ->schema([
                        RichEditor::make('body')
                            ->label(__('filament.knowledge_guides.fields.body'))
                            ->helperText(__('filament.knowledge_guides.fields.body_help'))
                            ->toolbarButtons([
                                ['bold', 'italic', 'link'],
                                ['h2', 'h3'],
                                ['bulletList', 'orderedList', 'blockquote'],
                                ['customBlocks', 'mergeTags'],
                                ['undo', 'redo'],
                            ])
                            ->customBlocks([KnowledgeActionBlock::class])
                            ->mergeTags([
                                'site_name' => __('filament.knowledge_guides.merge_tags.site_name'),
                                'association_role' => __('filament.knowledge_guides.merge_tags.association_role'),
                            ])
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('filament.knowledge_guides.sections.audience'))
                    ->description(__('filament.knowledge_guides.sections.audience_description'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('permissions_all')
                                    ->label(__('filament.knowledge_guides.fields.permissions_all'))
                                    ->options(self::permissionOptions(...))
                                    ->multiple()
                                    ->rules([fn (): Closure => self::onlyOptions(self::permissionOptions(...))])
                                    ->searchable()
                                    ->native(false),
                                Select::make('permissions_any')
                                    ->label(__('filament.knowledge_guides.fields.permissions_any'))
                                    ->options(self::permissionOptions(...))
                                    ->multiple()
                                    ->rules([fn (): Closure => self::onlyOptions(self::permissionOptions(...))])
                                    ->searchable()
                                    ->native(false),
                                Select::make('capabilities')
                                    ->label(__('filament.knowledge_guides.fields.capabilities'))
                                    ->helperText(__('filament.knowledge_guides.fields.capabilities_help'))
                                    ->options(self::capabilityOptions(...))
                                    ->multiple()
                                    ->rules([fn (): Closure => self::onlyOptions(self::capabilityOptions(...))])
                                    ->searchable()
                                    ->native(false),
                                Select::make('negated_capabilities')
                                    ->label(__('filament.knowledge_guides.fields.negated_capabilities'))
                                    ->options(self::capabilityOptions(...))
                                    ->multiple()
                                    ->rules([fn (): Closure => self::onlyOptions(self::capabilityOptions(...))])
                                    ->searchable()
                                    ->native(false),
                                Select::make('association_member')
                                    ->label(__('filament.knowledge_guides.fields.association_member'))
                                    ->options([
                                        SiteGuideFormData::ANYONE => __('filament.knowledge_guides.membership.any'),
                                        SiteGuideFormData::MEMBERS => __('filament.knowledge_guides.membership.members'),
                                        SiteGuideFormData::NON_MEMBERS => __('filament.knowledge_guides.membership.non_members'),
                                    ])
                                    ->default(SiteGuideFormData::ANYONE)
                                    ->required()
                                    ->native(false),
                            ]),
                    ]),
                Section::make(__('filament.knowledge_guides.sections.related'))
                    ->schema([
                        Select::make('related')
                            ->label(__('filament.knowledge_guides.fields.related'))
                            ->helperText(__('filament.knowledge_guides.fields.related_help'))
                            ->options(fn (?KnowledgeGuideModel $record): array => self::relatedOptions($record))
                            ->multiple()
                            ->rules([fn (?KnowledgeGuideModel $record): Closure => self::onlyOptions(fn (): array => self::relatedOptions($record))])
                            ->searchable()
                            ->native(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.knowledge_guides.columns.title'))
                    ->description(fn (KnowledgeGuideModel $record): string => $record->summary)
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('source')
                    ->label(__('filament.knowledge_guides.columns.source'))
                    ->badge()
                    ->formatStateUsing(fn (KnowledgeGuideModel $record): string => self::sourceLabel($record))
                    ->color(fn (KnowledgeGuideModel $record): string => self::isSiteGuide($record) ? 'primary' : 'gray')
                    ->visibleFrom('sm'),
                TextColumn::make('audience')
                    ->label(__('filament.knowledge_guides.columns.audience'))
                    ->formatStateUsing(fn (string $state): string => self::audienceOptions()[$state] ?? $state)
                    ->toggleable()
                    ->visibleFrom('xl'),
                TextColumn::make('category_id')
                    ->label(__('filament.knowledge_guides.columns.category'))
                    ->formatStateUsing(fn (string $state): string => self::categoryLabels()[$state] ?? $state)
                    ->toggleable()
                    ->visibleFrom('2xl'),
                TextColumn::make('status')
                    ->label(__('filament.knowledge_guides.columns.status'))
                    ->badge()
                    ->state(fn (KnowledgeGuideModel $record): string => self::status($record))
                    ->formatStateUsing(fn (string $state): string => __('filament.knowledge_guides.status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'published', 'visible' => 'success',
                        'hidden' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_featured')
                    ->label(__('filament.knowledge_guides.columns.featured'))
                    ->state(fn (KnowledgeGuideModel $record): bool => self::isFeatured($record))
                    ->boolean()
                    ->visibleFrom('md'),
                TextColumn::make('helpful')
                    ->label(__('filament.knowledge_guides.columns.helpful'))
                    ->alignCenter()
                    ->tooltip(__('filament.knowledge_guides.columns.votes_help', ['days' => self::METRICS_DAYS]))
                    ->state(fn (KnowledgeGuideModel $record): int => self::feedback()[$record->guide_id]['helpful'] ?? 0)
                    ->numeric()
                    ->visibleFrom('xl'),
                TextColumn::make('not_helpful')
                    ->label(__('filament.knowledge_guides.columns.not_helpful'))
                    ->alignCenter()
                    ->tooltip(__('filament.knowledge_guides.columns.votes_help', ['days' => self::METRICS_DAYS]))
                    ->state(fn (KnowledgeGuideModel $record): int => self::feedback()[$record->guide_id]['notHelpful'] ?? 0)
                    ->numeric()
                    ->visibleFrom('xl'),
            ])
            ->defaultSort('title')
            ->filters([
                SelectFilter::make('source')
                    ->label(__('filament.knowledge_guides.filters.source'))
                    ->options([
                        GuideSource::Site->value => __('filament.knowledge_guides.sources.site'),
                        GuideSource::Core->value => __('filament.knowledge_guides.sources.core'),
                        GuideSource::Module->value => __('filament.knowledge_guides.sources.modules'),
                    ]),
                SelectFilter::make('audience')
                    ->label(__('filament.knowledge_guides.filters.audience'))
                    ->options(self::audienceOptions()),
            ])
            // One menu: labelled row actions made the table overflow its container
            ->recordActions([
                ActionGroup::make([
                    RowDetailsAction::make(),
                    EditAction::make(),
                    Action::make('hide')
                        ->label(__('filament.knowledge_guides.actions.hide'))
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('filament.knowledge_guides.actions.hide_description'))
                        ->visible(fn (KnowledgeGuideModel $record): bool => self::canOverride($record) && ! self::isHidden($record))
                        ->action(function (KnowledgeGuideModel $record): void {
                            resolve(GuideOverrideServiceInterface::class)->setHidden(GuideId::fromString($record->guide_id), true);
                            Notification::make()->title(__('filament.knowledge_guides.notifications.hidden'))->success()->send();
                        }),
                    Action::make('show')
                        ->label(__('filament.knowledge_guides.actions.show'))
                        ->icon('heroicon-o-eye')
                        ->visible(fn (KnowledgeGuideModel $record): bool => self::canOverride($record) && self::isHidden($record))
                        ->action(function (KnowledgeGuideModel $record): void {
                            resolve(GuideOverrideServiceInterface::class)->setHidden(GuideId::fromString($record->guide_id), false);
                            Notification::make()->title(__('filament.knowledge_guides.notifications.shown'))->success()->send();
                        }),
                    Action::make('feature')
                        ->label(__('filament.knowledge_guides.actions.feature'))
                        ->icon('heroicon-o-star')
                        ->visible(fn (KnowledgeGuideModel $record): bool => self::canOverride($record))
                        ->fillForm(fn (KnowledgeGuideModel $record): array => ['featured' => match ($record->override?->featured) {
                            true => 'featured',
                            false => 'not_featured',
                            null => 'default',
                        }])
                        ->schema([
                            Select::make('featured')
                                ->label(__('filament.knowledge_guides.actions.feature_field'))
                                ->helperText(__('filament.knowledge_guides.actions.feature_help'))
                                ->options([
                                    'default' => __('filament.knowledge_guides.featured_states.default'),
                                    'featured' => __('filament.knowledge_guides.featured_states.featured'),
                                    'not_featured' => __('filament.knowledge_guides.featured_states.not_featured'),
                                ])
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (KnowledgeGuideModel $record, array $data): void {
                            resolve(GuideOverrideServiceInterface::class)->setFeatured(
                                GuideId::fromString($record->guide_id),
                                match ($data['featured'] ?? null) {
                                    'featured' => true,
                                    'not_featured' => false,
                                    default => null,
                                },
                            );
                            Notification::make()->title(__('filament.knowledge_guides.notifications.featured'))->success()->send();
                        }),
                    DeleteAction::make()
                        ->schema([
                            Checkbox::make('confirmation')
                                ->label(__('filament.knowledge_guides.actions.delete_confirmation_check'))
                                ->accepted()
                                ->required(),
                        ])
                        ->using(function (KnowledgeGuideModel $record): bool {
                            resolve(SiteGuideServiceInterface::class)->delete(GuideId::fromString($record->guide_id));

                            return true;
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKnowledgeGuides::route('/'),
            'create' => CreateKnowledgeGuide::route('/create'),
            'edit' => EditKnowledgeGuide::route('/{record}/edit'),
        ];
    }

    public static function locale(): string
    {
        $locale = config('knowledge.fallback_locale');

        return is_string($locale) && $locale !== '' ? $locale : 'es';
    }

    /**
     * @return array<string, string>
     */
    public static function categoryOptions(mixed $audience): array
    {
        if (! is_string($audience) || GuideAudience::tryFrom($audience) === null) {
            return [];
        }

        $options = [];
        foreach (KnowledgeCategoryModel::query()->where('audience', $audience)->orderBy('sort_order')->orderBy('category_id')->get() as $category) {
            $options[$category->category_id] = (string) __($category->label_key);
        }

        return $options;
    }

    /**
     * Rule for a multiple select: every chosen value must be one of the options (Filament only checks
     * single selects).
     *
     * @param  Closure(): array<string, string>  $options
     */
    private static function onlyOptions(Closure $options): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($options): void {
            $allowed = array_keys($options());
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (! in_array($item, $allowed, true)) {
                    $fail(__('validation.in', ['attribute' => $attribute]));

                    return;
                }
            }
        };
    }

    /**
     * @return array<string, string>
     */
    private static function audienceOptions(): array
    {
        return [
            GuideAudience::Site->value => __('filament.knowledge_guides.audiences.site'),
            GuideAudience::Panel->value => __('filament.knowledge_guides.audiences.panel'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function permissionOptions(): array
    {
        $options = [];
        foreach (resolve(PermissionRegistryInterface::class)->all() as $permission) {
            $options[$permission->key] = $permission->label;
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function capabilityOptions(): array
    {
        $keys = resolve(KnowledgeCapabilityRegistryInterface::class)->keys();
        sort($keys);

        return array_combine($keys, $keys);
    }

    /**
     * @return array<string, string>
     */
    private static function relatedOptions(?KnowledgeGuideModel $record): array
    {
        $options = [];
        $guides = KnowledgeGuideModel::query()
            ->where('locale', self::locale())
            ->where('active', true)
            ->when($record instanceof KnowledgeGuideModel, fn (Builder $query) => $query->where('guide_id', '!=', $record?->guide_id))
            ->orderBy('title')
            ->get(['guide_id', 'title', 'audience']);
        foreach ($guides as $guide) {
            $options[$guide->guide_id] = sprintf('%s (%s)', $guide->title, self::audienceOptions()[$guide->audience] ?? $guide->audience);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function categoryLabels(): array
    {
        return once(static function (): array {
            $labels = [];
            foreach (KnowledgeCategoryModel::query()->get(['category_id', 'label_key']) as $category) {
                $labels[$category->category_id] = (string) __($category->label_key);
            }

            return $labels;
        });
    }

    /**
     * @return array<string, array{helpful: int, notHelpful: int}>
     */
    private static function feedback(): array
    {
        return once(static fn (): array => resolve(KnowledgeMetricsServiceInterface::class)->feedbackTotals(self::METRICS_DAYS));
    }

    private static function sourceLabel(KnowledgeGuideModel $record): string
    {
        return match ($record->source) {
            GuideSource::Site->value => __('filament.knowledge_guides.sources.site'),
            GuideSource::Module->value => __('filament.knowledge_guides.sources.module', ['module' => $record->module ?? '']),
            default => __('filament.knowledge_guides.sources.core'),
        };
    }

    private static function status(KnowledgeGuideModel $record): string
    {
        if (self::isSiteGuide($record)) {
            return $record->active ? 'published' : 'draft';
        }

        return self::isHidden($record) ? 'hidden' : 'visible';
    }

    private static function isFeatured(KnowledgeGuideModel $record): bool
    {
        if (self::isSiteGuide($record)) {
            return $record->featured;
        }

        return $record->override->featured ?? $record->featured;
    }

    private static function isHidden(KnowledgeGuideModel $record): bool
    {
        return $record->override->hidden ?? false;
    }

    private static function isSiteGuide(KnowledgeGuideModel $record): bool
    {
        return $record->source === GuideSource::Site->value;
    }

    private static function canOverride(KnowledgeGuideModel $record): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('override', $record);
    }
}
