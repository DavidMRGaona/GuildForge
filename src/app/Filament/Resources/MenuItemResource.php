<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Application\Navigation\Services\RouteRegistryInterface;
use App\Domain\Navigation\Enums\LinkTarget;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Enums\MenuVisibility;
use App\Filament\Resources\MenuItemResource\Pages;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItemModel::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.admin');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.menu_items.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('filament.menu_items.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.menu_items.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        Select::make('location')
                            ->label(__('filament.menu_items.fields.location'))
                            ->options([
                                MenuLocation::Header->value => __('filament.menu_items.locations.header'),
                                MenuLocation::Footer->value => __('filament.menu_items.locations.footer'),
                            ])
                            ->required()
                            ->native(false)
                            ->live(),

                        Select::make('parent_id')
                            ->label(__('filament.menu_items.fields.parent'))
                            ->options(function (Get $get) {
                                $location = $get('location');
                                if (! $location) {
                                    return [];
                                }

                                return MenuItemModel::query()
                                    ->where('location', $location)
                                    ->whereNull('parent_id')
                                    ->pluck('label', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->nullable()
                            ->native(false)
                            ->helperText(__('filament.menu_items.fields.parent_help')),
                    ]),

                TextInput::make('label')
                    ->label(__('filament.menu_items.fields.label'))
                    ->required()
                    ->maxLength(100),

                Grid::make(2)
                    ->schema([
                        Select::make('route')
                            ->label(__('filament.menu_items.fields.page'))
                            ->options(fn () => resolve(RouteRegistryInterface::class)->getAvailableRoutes())
                            ->searchable()
                            ->nullable()
                            ->native(false)
                            ->helperText(__('filament.menu_items.fields.page_help')),

                        TextInput::make('url')
                            ->label(__('filament.menu_items.fields.url'))
                            ->helperText(__('filament.menu_items.fields.url_help'))
                            ->url()
                            ->nullable(),
                    ]),

                Grid::make(3)
                    ->schema([
                        TextInput::make('icon')
                            ->label(__('filament.menu_items.fields.icon'))
                            ->placeholder('heroicon-o-home')
                            ->helperText(__('filament.menu_items.fields.icon_help'))
                            ->nullable(),

                        Select::make('target')
                            ->label(__('filament.menu_items.fields.target'))
                            ->options([
                                LinkTarget::Self->value => __('filament.menu_items.targets.self'),
                                LinkTarget::Blank->value => __('filament.menu_items.targets.blank'),
                            ])
                            ->default(LinkTarget::Self->value)
                            ->native(false),

                        TextInput::make('sort_order')
                            ->label(__('filament.menu_items.fields.sort_order'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),

                Grid::make(2)
                    ->schema([
                        Select::make('visibility')
                            ->label(__('filament.menu_items.fields.visibility'))
                            ->options([
                                MenuVisibility::Public->value => __('filament.menu_items.visibility.public'),
                                MenuVisibility::Authenticated->value => __('filament.menu_items.visibility.authenticated'),
                                MenuVisibility::Guests->value => __('filament.menu_items.visibility.guests'),
                                MenuVisibility::Permission->value => __('filament.menu_items.visibility.permission'),
                            ])
                            ->default(MenuVisibility::Public->value)
                            ->native(false)
                            ->live(),

                        Select::make('permissions')
                            ->label(__('filament.menu_items.fields.permissions'))
                            ->options(self::getAvailablePermissions())
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('visibility') === MenuVisibility::Permission->value)
                            ->helperText(__('filament.menu_items.fields.permissions_help')),
                    ]),

                Toggle::make('is_active')
                    ->label(__('filament.menu_items.fields.is_active'))
                    ->default(true)
                    ->helperText(__('filament.menu_items.fields.is_active_help')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label(__('filament.menu_items.fields.label'))
                    ->searchable()
                    ->sortable()
                    ->description(function (MenuItemModel $record): string {
                        if ($record->parent_id === null) {
                            return '';
                        }
                        $parentLabel = $record->parent !== null ? $record->parent->label : '';

                        return '↳ '.__('filament.menu_items.submenu_of', ['parent' => $parentLabel]);
                    }),

                TextColumn::make('route')
                    ->label(__('filament.menu_items.fields.link'))
                    ->formatStateUsing(function (MenuItemModel $record): string {
                        if ($record->route) {
                            $routes = resolve(RouteRegistryInterface::class)->getAvailableRoutes();

                            return $routes[$record->route] ?? $record->route;
                        }
                        if ($record->url) {
                            return $record->url;
                        }

                        return '-';
                    })
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('visibility')
                    ->label(__('filament.menu_items.fields.visibility'))
                    ->badge()
                    ->color(fn (MenuVisibility $state): string => match ($state) {
                        MenuVisibility::Public => 'success',
                        MenuVisibility::Authenticated => 'warning',
                        MenuVisibility::Guests => 'info',
                        MenuVisibility::Permission => 'danger',
                    })
                    ->formatStateUsing(fn (MenuVisibility $state): string => match ($state) {
                        MenuVisibility::Public => __('filament.menu_items.visibility.public'),
                        MenuVisibility::Authenticated => __('filament.menu_items.visibility.authenticated'),
                        MenuVisibility::Guests => __('filament.menu_items.visibility.guests'),
                        MenuVisibility::Permission => __('filament.menu_items.visibility.permission'),
                    }),

                IconColumn::make('is_active')
                    ->label(__('filament.menu_items.fields.is_active'))
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label(__('filament.menu_items.fields.sort_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('module')
                    ->label(__('filament.menu_items.fields.module'))
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'core' ? 'warning' : 'gray')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'core' => __('filament.menu_items.modules.core'),
                        null => '-',
                        default => $state,
                    }),
            ])
            ->filters([
                SelectFilter::make('visibility')
                    ->label(__('filament.menu_items.fields.visibility'))
                    ->options([
                        MenuVisibility::Public->value => __('filament.menu_items.visibility.public'),
                        MenuVisibility::Authenticated->value => __('filament.menu_items.visibility.authenticated'),
                        MenuVisibility::Guests->value => __('filament.menu_items.visibility.guests'),
                        MenuVisibility::Permission->value => __('filament.menu_items.visibility.permission'),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (MenuItemModel $record): bool => $record->module !== null),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->using(function (Collection $records): void {
                        $records
                            ->filter(fn (Model $record): bool => $record->getAttribute('module') === null)
                            ->each(fn (Model $record) => $record->delete());
                    }),
            ])
            ->reorderable('sort_order')
            ->modifyQueryUsing(
                fn (Builder $query) => $query
                    ->with('parent')
                    ->selectRaw('menu_items.*, COALESCE(parent.sort_order, menu_items.sort_order) as parent_sort_order')
                    ->leftJoin('menu_items as parent', 'menu_items.parent_id', '=', 'parent.id')
                    ->orderBy('parent_sort_order')
                    ->orderByRaw('menu_items.parent_id IS NOT NULL')
                    ->orderBy('menu_items.sort_order')
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }

    /**
     * Get list of available permissions for the select.
     *
     * @return array<string, string>
     */
    protected static function getAvailablePermissions(): array
    {
        // Get permissions from the database
        try {
            $permissions = \App\Infrastructure\Persistence\Eloquent\Models\PermissionModel::query()
                ->orderBy('key')
                ->pluck('label', 'key')
                ->toArray();

            return $permissions;
        } catch (\Throwable) {
            return [];
        }
    }
}
