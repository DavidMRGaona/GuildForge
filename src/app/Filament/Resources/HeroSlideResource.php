<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Actions\RowDetailsAction;
use App\Filament\Forms\Components\MediaLibraryPicker;
use App\Filament\Resources\HeroSlideResource\Pages\CreateHeroSlide;
use App\Filament\Resources\HeroSlideResource\Pages\EditHeroSlide;
use App\Filament\Resources\HeroSlideResource\Pages\ListHeroSlides;
use App\Filament\Support\PanelNavigationGroup;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class HeroSlideResource extends BaseResource
{
    protected static ?string $model = HeroSlideModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string
    {
        return PanelNavigationGroup::Site->getLabel();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.hero_slides.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('filament.hero_slides.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.hero_slides.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                MediaLibraryPicker::attachTo(
                    FileUpload::make('image_public_id')
                        ->label(__('Imagen'))
                        ->image()
                        ->disk('images')
                        ->directory(fn (): string => 'hero-slides/'.now()->format('Y/m'))
                        ->getUploadedFileNameForStorageUsing(
                            fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.$file->getClientOriginalExtension()
                        )
                        ->maxSize(2048)
                        ->nullable()
                        ->columnSpanFull(),
                ),
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
                    ->disk('images')
                    ->visibleFrom('sm'),
                TextColumn::make('title')
                    ->label(__('Título'))
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('subtitle')
                    ->label(__('Subtítulo'))
                    ->searchable()
                    ->limit(30)
                    ->toggleable()
                    ->visibleFrom('xl'),
                IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label(__('Orden'))
                    ->alignCenter()
                    ->sortable()
                    ->visibleFrom('sm'),
            ])
            // The whole row opens the record: «Ver detalles» shows what narrow screens hide (core#399)
            ->recordActions([
                RowDetailsAction::makeIconButton(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHeroSlides::route('/'),
            'create' => CreateHeroSlide::route('/create'),
            'edit' => EditHeroSlide::route('/{record}/edit'),
        ];
    }
}
