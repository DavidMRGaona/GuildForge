<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasExtendableFormSections;
use App\Filament\Concerns\HasExtendableRelations;
use App\Filament\Resources\EventResource\Pages;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('evento');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Eventos');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ->rules(['alpha_dash']),
                RichEditor::make('description')
                    ->label(__('Descripción'))
                    ->required()
                    ->columnSpanFull(),
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
                Toggle::make('is_published')
                    ->label(__('Publicado'))
                    ->default(false),

                // Extended form sections from modules
                ...static::getExtendedFormSections(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_public_id')
                    ->label(__('Imagen'))
                    ->square()
                    ->disk('images'),
                TextColumn::make('title')
                    ->label(__('Título'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('start_date')
                    ->label(__('Fecha'))
                    ->formatStateUsing(fn ($record) => $record->start_date->format('d/m/Y').' - '.$record->end_date->format('d/m/Y'))
                    ->sortable(),
                TextColumn::make('location')
                    ->label(__('Ubicación'))
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),
                IconColumn::make('is_published')
                    ->label(__('Publicado'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label(__('Publicado')),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
