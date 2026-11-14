<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Proposals\Enums\ProposalType;
use App\Filament\Actions\RowDetailsAction;
use App\Filament\Concerns\HasExtendableFormSections;
use App\Filament\Concerns\HasExtendableRelations;
use App\Filament\Forms\Components\MediaLibraryPicker;
use App\Filament\Forms\RichEditor\EditorImages;
use App\Filament\Proposals\ProposalRequestsSection;
use App\Filament\Proposals\ProposalReviewPanel;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Support\PanelNavigationGroup;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EventResource extends BaseResource
{
    use HasExtendableFormSections;
    use HasExtendableRelations;

    protected static ?string $model = EventModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): string
    {
        return PanelNavigationGroup::Content->getLabel();
    }

    /**
     * Panel URLs carry the slug (EventModel::getRouteKeyName()), but links kept in mails and in the
     * bell carry the id, which stays when a new title changes the slug (core#652). Both go through
     * the resource's query, so whatever it hides stays hidden.
     */
    public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        $record = parent::resolveRecordRouteBinding($key, $modifyQuery);

        if ($record instanceof Model || ! Str::isUuid((string) $key)) {
            return $record;
        }

        $query = static::getRecordRouteBindingEloquentQuery();

        if ($modifyQuery instanceof Closure) {
            $query = $modifyQuery($query) ?? $query;
        }

        return $query->whereKey((string) $key)->first();
    }

    public static function getNavigationBadge(): ?string
    {
        return ProposalReviewPanel::navigationBadge(ProposalType::Event, EventModel::class);
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('filament.proposals.navigation_badge');
    }

    public static function getModelLabel(): string
    {
        return __('evento');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Eventos');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ProposalReviewPanel::reviewSection(),
                ProposalRequestsSection::make(),
                TextInput::make('title')
                    ->label(__('Título'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                TextInput::make('slug')
                    ->label(__('Slug'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->rules(['alpha_dash'])
                    ->helperText(__('filament.proposals.slug_hint')),
                Section::make(__('Fechas'))
                    ->schema([
                        DateTimePicker::make('start_date')
                            ->label(__('Fecha de inicio'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y H:i'),
                        DateTimePicker::make('end_date')
                            ->label(__('Fecha de fin'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->after('start_date'),
                    ])->columns(2),
                TextInput::make('location')
                    ->label(__('Ubicación'))
                    ->maxLength(255),
                EditorImages::configure(RichEditor::make('description')
                    ->label(__('Descripción'))
                    ->required()
                    ->columnSpanFull(), EditorImageOrigin::Events),
                MediaLibraryPicker::attachTo(
                    FileUpload::make('image_public_id')
                        ->label(__('Imagen'))
                        ->image()
                        ->disk('images')
                        ->directory(fn (): string => 'events/'.now()->format('Y/m'))
                        ->getUploadedFileNameForStorageUsing(
                            fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.$file->getClientOriginalExtension()
                        )
                        ->maxSize(2048)
                        ->nullable(),
                ),
                Select::make('additional_tag_ids')
                    ->label(__('filament.tags.fields.additional'))
                    ->options(fn (Get $get) => TagModel::whereNotNull('parent_id')
                        ->forType('events')
                        ->when($get('category_id'), fn (Builder $q, string $id) => $q->where('id', '!=', $id))
                        ->ordered()
                        ->get()
                        ->mapWithKeys(fn (TagModel $tag): array => [$tag->id => $tag->getIndentedNameForSelect()]))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->afterStateHydrated(function (Select $component, ?Model $record): void {
                        if ($record instanceof EventModel) {
                            $tagIds = $record->tags()
                                ->whereNotNull('parent_id')
                                ->pluck('id')
                                ->toArray();
                            $component->state($tagIds);
                        }
                    }),
                // What the web never asks for, after what it shares with the panel (core#655)
                ProposalReviewPanel::reviewerBlock([
                    Select::make('category_id')
                        ->label(__('filament.tags.fields.category'))
                        ->options(fn () => TagModel::roots()
                            ->forType('events')
                            ->ordered()
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->afterStateHydrated(function (Select $component, ?Model $record): void {
                            if ($record instanceof EventModel) {
                                $categoryId = $record->tags()
                                    ->whereNull('parent_id')
                                    ->first()?->id;
                                $component->state($categoryId);
                            }
                        }),
                    Section::make(__('Precios'))
                        ->schema([
                            TextInput::make('member_price')
                                ->label(__('Precio socios'))
                                ->numeric()
                                ->prefix('€')
                                ->minValue(0)
                                ->step(0.01),
                            TextInput::make('non_member_price')
                                ->label(__('Precio no socios'))
                                ->numeric()
                                ->prefix('€')
                                ->minValue(0)
                                ->step(0.01),
                        ])->columns(2),
                    Section::make(__('Enlaces de descarga'))
                        ->schema([
                            Repeater::make('download_links')
                                ->label(__('Enlaces de descarga'))
                                ->schema([
                                    TextInput::make('label')
                                        ->label(__('Etiqueta'))
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('url')
                                        ->label(__('URL'))
                                        ->required()
                                        ->url()
                                        ->maxLength(2048),
                                    TextInput::make('description')
                                        ->label(__('Descripción'))
                                        ->maxLength(500),
                                ])
                                ->columns(1)
                                ->collapsible()
                                ->defaultItems(0),
                        ]),
                ]),
                Toggle::make('is_published')
                    ->label(__('Publicado'))
                    ->default(false)
                    ->disabled(fn (?Model $record): bool => ! ProposalReviewPanel::mayPublishWithToggle($record))
                    ->helperText(fn (?Model $record): ?string => ProposalReviewPanel::publishLockedHint($record)),

                ...self::moduleFormSections(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_public_id')
                    ->label(__('Imagen'))
                    ->square()
                    ->disk('images')
                    ->visibleFrom('md'),
                TextColumn::make('title')
                    ->label(__('Título'))
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('start_date')
                    ->label(__('Fecha'))
                    ->formatStateUsing(function (EventModel $record): string {
                        $timezone = app(TimezoneServiceInterface::class);

                        return $timezone->format($record->start_date).' – '.(
                            $timezone->format($record->start_date, 'Y-m-d') === $timezone->format($record->end_date, 'Y-m-d')
                                ? $timezone->format($record->end_date, 'H:i')
                                : $timezone->format($record->end_date)
                        );
                    })
                    ->sortable()
                    ->visibleFrom('sm'),
                TextColumn::make('location')
                    ->label(__('Ubicación'))
                    ->searchable()
                    ->limit(30)
                    ->toggleable()
                    ->visibleFrom('xl'),
                IconColumn::make('is_published')
                    ->label(__('Publicado'))
                    ->boolean(),
                ProposalReviewPanel::statusColumn()
                    ->visibleFrom('2xl'),
                ProposalReviewPanel::proposerColumn()
                    ->visibleFrom('2xl'),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label(__('Publicado')),
                ProposalReviewPanel::statusFilter(),
            ])
            // The whole row opens the record: «Ver detalles» shows what narrow screens hide (core#399)
            ->recordActions([
                RowDetailsAction::makeIconButton(),
            ])
            ->defaultSort('start_date', 'desc');
    }

    /**
     * What modules add to the form (extendFormSections()). Hidden while the event is a proposal
     * not approved yet: its module requests are the only way to configure them until then
     * (core#575), and a hidden section is neither validated nor saved. Same layout as the form.
     *
     * @return list<Group>
     */
    private static function moduleFormSections(): array
    {
        $sections = static::getExtendedFormSections();

        if ($sections === []) {
            return [];
        }

        return [
            Group::make($sections)
                ->columns(2)
                ->columnSpanFull()
                ->hidden(fn (?Model $record): bool => ProposalReviewPanel::isUnapproved($record)),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
