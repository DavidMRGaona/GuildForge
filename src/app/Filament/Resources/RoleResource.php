<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = RoleModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('filament.roles.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.roles.pluralLabel');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('filament.roles.sections.general'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament.roles.fields.name'))
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?RoleModel $record): bool => $record !== null && $record->is_protected)
                            ->helperText(__('filament.roles.fields.nameHelp')),
                        TextInput::make('display_name')
                            ->label(__('filament.roles.fields.displayName'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('filament.roles.fields.description'))
                            ->rows(3)
                            ->maxLength(500),
                        Toggle::make('is_protected')
                            ->label(__('filament.roles.fields.isProtected'))
                            ->helperText(__('filament.roles.fields.isProtectedHelp'))
                            ->disabled()
                            ->dehydrated(false),
                    ]),
                Section::make(__('filament.roles.sections.permissions'))
                    ->description(__('filament.roles.sections.permissionsDescription'))
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label(__('filament.roles.fields.permissions'))
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'label',
                            )
                            ->columns(2)
                            ->searchable()
                            ->bulkToggleable()
                            ->gridDirection('row')
                            ->descriptions(
                                PermissionModel::query()
                                    ->pluck('key', 'id')
                                    ->toArray()
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('filament.roles.fields.displayName'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('filament.roles.fields.name'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('permissions_count')
                    ->label(__('filament.roles.fields.permissionsCount'))
                    ->counts('permissions')
                    ->badge()
                    ->color('success'),
                TextColumn::make('users_count')
                    ->label(__('filament.roles.fields.usersCount'))
                    ->counts('users')
                    ->badge()
                    ->color('info'),
                IconColumn::make('is_protected')
                    ->label(__('filament.roles.fields.isProtected'))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->label(__('filament.roles.fields.createdAt'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('display_name')
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (RoleModel $record): bool => $record->is_protected),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
