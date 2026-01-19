<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\HeroSlideResource\Pages;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class HeroSlideResource extends Resource
{
    protected static ?string $model = HeroSlideModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('Slide');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Hero slides');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label(__('Título'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('subtitle')
                    ->label(__('Subtítulo'))
                    ->maxLength(255),
                TextInput::make('button_text')
                    ->label(__('Texto del botón'))
                    ->maxLength(255),
                TextInput::make('button_url')
                    ->label(__('URL del botón'))
                    ->maxLength(255),
                FileUpload::make('image_public_id')
                    ->label(__('Imagen'))
                    ->image()
                    ->disk('images')
                    ->directory(fn (): string => 'hero-slides/' . now()->format('Y/m'))
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => Str::uuid()->toString() . '.' . $file->getClientOriginalExtension()
                    )
                    ->maxSize(2048)
                    ->nullable()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(__('Activo'))
                    ->default(false),
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
                TextColumn::make('subtitle')
                    ->label(__('Subtítulo'))
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label(__('Orden'))
                    ->sortable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHeroSlides::route('/'),
            'create' => Pages\CreateHeroSlide::route('/create'),
            'edit' => Pages\EditHeroSlide::route('/{record}/edit'),
        ];
    }
}
