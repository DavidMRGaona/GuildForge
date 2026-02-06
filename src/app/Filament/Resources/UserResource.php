<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\DTOs\ImageOptimizationSettingsDTO;
use App\Application\Services\ImageOptimizationServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Filament\Resources\UserResource\Pages;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UserResource extends BaseResource
{
    protected static ?string $model = UserModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('usuario');
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
                    ->directory(fn (): string => 'avatars/'.now()->format('Y/m'))
                    ->saveUploadedFileUsing(static function (TemporaryUploadedFile $file): string {
                        $imageOptimizer = app(ImageOptimizationServiceInterface::class);
                        $avatarSettings = ImageOptimizationSettingsDTO::withOverrides([
                            'maxWidth' => 512,
                            'maxHeight' => 512,
                            'quality' => 90,
                            'minSizeBytes' => 0,
                        ]);

                        $contents = $file->get();
                        if ($contents === false) {
                            throw new \RuntimeException('Failed to read uploaded file');
                        }

                        $optimizedContents = $imageOptimizer->optimize(
                            $contents,
                            $file->getMimeType(),
                            $avatarSettings
                        );

                        $directory = 'avatars/'.now()->format('Y/m');
                        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
                        $path = $directory.'/'.$filename;

                        Storage::disk('images')->put($path, $optimizedContents);

                        return $path;
                    })
                    ->nullable(),
                Section::make(__('filament.users.sections.roles'))
                    ->description(__('filament.users.sections.rolesDescription'))
                    ->schema([
                        CheckboxList::make('roles')
                            ->label(__('filament.users.fields.roles'))
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'display_name',
                            )
                            ->columns(2)
                            ->searchable()
                            ->bulkToggleable()
                            ->descriptions(
                                RoleModel::query()
                                    ->pluck('description', 'id')
                                    ->toArray()
                            ),
                    ]),
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
                TextColumn::make('roles.display_name')
                    ->label(__('filament.users.fields.roles'))
                    ->badge()
                    ->separator(', ')
                    ->color(fn (string $state): string => match ($state) {
                        'Administrator' => 'danger',
                        'Editor' => 'warning',
                        default => 'success',
                    }),
                IconColumn::make('email_verified_at')
                    ->label(__('filament.users.fields.emailVerified'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn (UserModel $record): bool => $record->email_verified_at !== null),
                TextColumn::make('status')
                    ->label(__('filament.users.fields.status'))
                    ->badge()
                    ->getStateUsing(function (UserModel $record): string {
                        if ($record->anonymized_at !== null) {
                            return __('filament.users.status.anonymized');
                        }
                        if ($record->trashed()) {
                            return __('filament.users.status.deactivated');
                        }

                        return __('filament.users.status.active');
                    })
                    ->color(fn (UserModel $record): string => match (true) {
                        $record->anonymized_at !== null => 'gray',
                        $record->trashed() => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label(__('Fecha de creación'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('filament.users.fields.roles'))
                    ->relationship('roles', 'display_name')
                    ->multiple()
                    ->preload(),
                TrashedFilter::make()
                    ->label(__('filament.users.filters.status'))
                    ->default('with'),
            ])
            ->actions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('sendVerificationEmail')
                        ->label(__('filament.users.actions.sendVerificationEmail'))
                        ->icon('heroicon-o-envelope')
                        ->color('warning')
                        ->visible(fn (UserModel $record): bool => $record->email_verified_at === null && ! $record->trashed())
                        ->requiresConfirmation()
                        ->modalHeading(__('filament.users.actions.sendVerificationEmail'))
                        ->modalDescription(__('filament.users.actions.sendVerificationEmailConfirm'))
                        ->action(function (UserModel $record): void {
                            $record->sendEmailVerificationNotification();
                            Notification::make()
                                ->title(__('filament.users.actions.verificationEmailSent'))
                                ->success()
                                ->send();
                        }),
                    Action::make('sendPasswordResetEmail')
                        ->label(__('filament.users.actions.sendPasswordResetEmail'))
                        ->icon('heroicon-o-key')
                        ->color('info')
                        ->visible(fn (UserModel $record): bool => ! $record->trashed())
                        ->requiresConfirmation()
                        ->modalHeading(__('filament.users.actions.sendPasswordResetEmail'))
                        ->modalDescription(__('filament.users.actions.sendPasswordResetEmailConfirm'))
                        ->action(function (UserModel $record): void {
                            $status = Password::sendResetLink(['email' => $record->email]);
                            if ($status === Password::RESET_LINK_SENT) {
                                Notification::make()
                                    ->title(__('filament.users.actions.passwordResetEmailSent'))
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(__('filament.users.actions.passwordResetEmailFailed'))
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('deactivate')
                        ->label(__('filament.users.actions.deactivate'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('warning')
                        ->visible(fn (UserModel $record): bool => ! $record->trashed() && $record->id !== Auth::id())
                        ->requiresConfirmation()
                        ->modalHeading(__('filament.users.actions.deactivate'))
                        ->modalDescription(__('filament.users.actions.deactivateConfirm'))
                        ->modalIcon('heroicon-o-no-symbol')
                        ->modalIconColor('warning')
                        ->action(function (UserModel $record): void {
                            if ($record->id === Auth::id()) {
                                Notification::make()
                                    ->title(__('filament.users.actions.cannotDeactivateSelf'))
                                    ->danger()
                                    ->send();

                                return;
                            }
                            $record->delete();
                            Notification::make()
                                ->title(__('filament.users.actions.deactivated'))
                                ->success()
                                ->send();
                        }),
                    RestoreAction::make()
                        ->label(__('filament.users.actions.restore'))
                        ->modalHeading(__('filament.users.actions.restore'))
                        ->modalDescription(__('filament.users.actions.restoreConfirm'))
                        ->successNotificationTitle(__('filament.users.actions.restored')),
                    Action::make('anonymize')
                        ->label(__('filament.users.actions.anonymize'))
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->visible(fn (UserModel $record): bool => $record->trashed() && $record->anonymized_at === null && $record->id !== Auth::id())
                        ->modalHeading(fn (UserModel $record): string => __('filament.users.actions.anonymizeModal.heading', ['name' => $record->display_name ?? $record->name]))
                        ->modalDescription(function (UserModel $record): string {
                            $userService = app(UserServiceInterface::class);
                            $contentCounts = $userService->countUserContent($record->id);
                            $articlesCount = $contentCounts['articles'];

                            if ($articlesCount === 0) {
                                return __('filament.users.actions.anonymizeModal.noContent');
                            }

                            $contentText = trans_choice('filament.users.actions.anonymizeModal.articlesCount', $articlesCount, ['count' => $articlesCount]);

                            return __('filament.users.actions.anonymizeModal.hasContent', ['content' => $contentText]);
                        })
                        ->modalIcon('heroicon-o-exclamation-triangle')
                        ->modalIconColor('danger')
                        ->form(function (UserModel $record): array {
                            $userService = app(UserServiceInterface::class);
                            $contentCounts = $userService->countUserContent($record->id);
                            $articlesCount = $contentCounts['articles'];

                            if ($articlesCount === 0) {
                                return [];
                            }

                            return [
                                Radio::make('content_action')
                                    ->label(__('filament.users.actions.anonymizeModal.contentActionLabel'))
                                    ->options([
                                        'transfer' => __('filament.users.actions.anonymizeModal.transfer'),
                                        'anonymize' => __('filament.users.actions.anonymizeModal.keepAnonymized'),
                                    ])
                                    ->default('anonymize')
                                    ->required()
                                    ->live(),
                                Select::make('transfer_to_user_id')
                                    ->label(__('filament.users.actions.anonymizeModal.transferTo'))
                                    ->options(fn () => UserModel::query()
                                        ->whereNull('deleted_at')
                                        ->whereNull('anonymized_at')
                                        ->where('id', '!=', $record->id)
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->visible(fn (Get $get): bool => $get('content_action') === 'transfer'),
                            ];
                        })
                        ->modalSubmitActionLabel(__('filament.users.actions.anonymizeModal.confirm'))
                        ->action(function (UserModel $record, array $data): void {
                            if ($record->id === Auth::id()) {
                                Notification::make()
                                    ->title(__('filament.users.actions.cannotAnonymizeSelf'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $userService = app(UserServiceInterface::class);
                            $contentCounts = $userService->countUserContent($record->id);
                            $articlesCount = $contentCounts['articles'];

                            if ($articlesCount === 0) {
                                $userService->anonymize($record->id);
                            } else {
                                $dto = new AnonymizeUserDTO(
                                    userId: $record->id,
                                    contentAction: $data['content_action'] ?? 'anonymize',
                                    transferToUserId: $data['transfer_to_user_id'] ?? null,
                                );
                                $userService->anonymizeWithContentTransfer($dto);
                            }

                            Notification::make()
                                ->title(__('filament.users.actions.anonymized'))
                                ->success()
                                ->send();
                        }),
                ])->icon('heroicon-m-ellipsis-vertical'),
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

    /**
     * @return Builder<UserModel>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
