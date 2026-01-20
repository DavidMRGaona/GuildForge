<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryResource\Pages;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class GalleryResource extends Resource
{
    protected static ?string $model = GalleryModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('galería');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Galerías');
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
                Textarea::make('description')
                    ->label(__('Descripción'))
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Select::make('category_id')
                    ->label(__('filament.tags.fields.category'))
                    ->options(fn () => TagModel::roots()
                        ->forType('galleries')
                        ->ordered()
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->afterStateHydrated(function (Select $component, ?Model $record): void {
                        if ($record instanceof GalleryModel) {
                            $categoryId = $record->tags()
                                ->whereNull('parent_id')
                                ->first()?->id;
                            $component->state($categoryId);
                        }
                    }),
                Select::make('additional_tag_ids')
                    ->label(__('filament.tags.fields.additional'))
                    ->options(fn (Get $get) => TagModel::whereNotNull('parent_id')
                        ->forType('galleries')
                        ->when($get('category_id'), fn (Builder $q, string $id) => $q->where('id', '!=', $id))
                        ->ordered()
                        ->get()
                        ->mapWithKeys(fn (TagModel $tag): array => [$tag->id => $tag->getIndentedNameForSelect()]))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->afterStateHydrated(function (Select $component, ?Model $record): void {
                        if ($record instanceof GalleryModel) {
                            $tagIds = $record->tags()
                                ->whereNotNull('parent_id')
                                ->pluck('id')
                                ->toArray();
                            $component->state($tagIds);
                        }
                    }),
                Toggle::make('is_published')
                    ->label(__('Publicada'))
                    ->default(false),
                Toggle::make('is_featured')
                    ->label(__('Destacada en inicio'))
                    ->helperText(__('Solo una galería puede estar destacada. Al activar esta opción se desactivará en las demás.'))
                    ->default(false)
                    ->afterStateUpdated(function (bool $state): void {
                        if ($state) {
                            GalleryModel::where('is_featured', true)->update(['is_featured' => false]);
                        }
                    }),
                Repeater::make('photos')
                    ->label(__('Fotos'))
                    ->helperText(__('La primera foto será la portada de la galería. Arrastra para reordenar.'))
                    ->relationship()
                    ->schema([
                        FileUpload::make('image_public_id')
                            ->label(__('Imagen'))
                            ->image()
                            ->disk('images')
                            ->directory(fn (): string => 'galleries/' . now()->format('Y/m'))
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::uuid()->toString() . '.' . $file->getClientOriginalExtension()
                            )
                            ->maxSize(10240)
                            ->required(),
                        TextInput::make('caption')
                            ->label(__('Descripción'))
                            ->maxLength(255),
                    ])
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['caption'] ?? null)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Título'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('photos_count')
                    ->label(__('Fotos'))
                    ->counts('photos')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label(__('Publicada'))
                    ->boolean(),
                IconColumn::make('is_featured')
                    ->label(__('Destacada'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('Creada'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label(__('Publicada')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGalleries::route('/'),
            'create' => Pages\CreateGallery::route('/create'),
            'edit' => Pages\EditGallery::route('/{record}/edit'),
        ];
    }
}
