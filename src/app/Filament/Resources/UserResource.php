<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UserResource extends Resource
{
    protected static ?string $model = UserModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Usuario');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Usuarios');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('Nombre de usuario'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('display_name')
                    ->label(__('Nombre para mostrar'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('Correo electrónico'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label(__('Contraseña'))
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->minLength(8)
                    ->maxLength(255),
                FileUpload::make('avatar_public_id')
                    ->label(__('Avatar'))
                    ->image()
                    ->disk('images')
                    ->directory(fn (): string => 'avatars/' . now()->format('Y/m'))
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => Str::uuid()->toString() . '.' . $file->getClientOriginalExtension()
                    )
                    ->nullable(),
                Select::make('role')
                    ->label(__('Rol'))
                    ->options(
                        collect(UserRole::cases())
                            ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->label()])
                            ->toArray()
                    )
                    ->required()
                    ->default(UserRole::Member->value),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_public_id')
                    ->label(__('Avatar'))
                    ->circular()
                    ->disk('images'),
                TextColumn::make('name')
                    ->label(__('Nombre de usuario'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('display_name')
                    ->label(__('Nombre para mostrar'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Correo electrónico'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label(__('Rol'))
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::Admin => 'danger',
                        UserRole::Editor => 'warning',
                        UserRole::Member => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label(__('Fecha de creación'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('Rol'))
                    ->options(
                        collect(UserRole::cases())
                            ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->label()])
                            ->toArray()
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
