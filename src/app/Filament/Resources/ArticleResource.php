<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Filament\Resources\BaseResource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ArticleResource extends BaseResource
{
    protected static ?string $model = ArticleModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('artículo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Artículos');
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
                RichEditor::make('content')
                    ->label(__('Contenido'))
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('excerpt')
                    ->label(__('Extracto'))
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
                FileUpload::make('featured_image_public_id')
                    ->label(__('Imagen destacada'))
                    ->image()
                    ->disk('images')
                    ->directory(fn (): string => 'articles/'.now()->format('Y/m'))
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.$file->getClientOriginalExtension()
                    )
                    ->maxSize(2048)
                    ->nullable(),
                Select::make('author_id')
                    ->label(__('Autor'))
                    ->relationship('author', 'display_name')
                    ->getOptionLabelFromRecordUsing(fn (UserModel $record): string => $record->display_name ?? $record->name)
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('category_id')
                    ->label(__('filament.tags.fields.category'))
                    ->options(fn () => TagModel::roots()
                        ->forType('articles')
                        ->ordered()
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->afterStateHydrated(function (Select $component, ?Model $record): void {
                        if ($record instanceof ArticleModel) {
                            $categoryId = $record->tags()
                                ->whereNull('parent_id')
                                ->first()?->id;
                            $component->state($categoryId);
                        }
                    }),
                Select::make('additional_tag_ids')
                    ->label(__('filament.tags.fields.additional'))
                    ->options(fn (Get $get) => TagModel::whereNotNull('parent_id')
                        ->forType('articles')
                        ->when($get('category_id'), fn (Builder $q, string $id) => $q->where('id', '!=', $id))
                        ->ordered()
                        ->get()
                        ->mapWithKeys(fn (TagModel $tag): array => [$tag->id => $tag->getIndentedNameForSelect()]))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->afterStateHydrated(function (Select $component, ?Model $record): void {
                        if ($record instanceof ArticleModel) {
                            $tagIds = $record->tags()
                                ->whereNotNull('parent_id')
                                ->pluck('id')
                                ->toArray();
                            $component->state($tagIds);
                        }
                    }),
                Toggle::make('is_published')
                    ->label(__('Publicado'))
                    ->default(false)
                    ->live(),
                DateTimePicker::make('published_at')
                    ->label(__('Fecha de publicación'))
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->visible(fn (Get $get): bool => $get('is_published') === true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('featured_image_public_id')
                    ->label(__('Imagen'))
                    ->square()
                    ->disk('images'),
                TextColumn::make('title')
                    ->label(__('Título'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('author.display_name')
                    ->label(__('Autor'))
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label(__('Publicado'))
                    ->boolean(),
                TextColumn::make('published_at')
                    ->label(__('Fecha de publicación'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder(__('Sin publicar')),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label(__('Publicado')),
                SelectFilter::make('author_id')
                    ->label(__('Autor'))
                    ->relationship('author', 'display_name'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
