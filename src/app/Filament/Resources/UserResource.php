<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\BaseRoleRule;
use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\DTOs\ImageOptimizationSettingsDTO;
use App\Application\Services\ImageOptimizationServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Filament\Actions\ImpersonateAction;
use App\Filament\Actions\RowDetailsAction;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\RelationManagers\ImpersonationsAsImpersonatorRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\ImpersonationsAsTargetRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\RoleAuditRelationManager;
use App\Filament\Support\PanelNavigationGroup;
use App\Infrastructure\Authorization\Services\InactiveRoleHolders;
use App\Infrastructure\Authorization\Services\RoleHolderLimitGuard;
use App\Infrastructure\Authorization\Services\TwoFactorHolders;
use App\Infrastructure\Images\AvatarPath;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class UserResource extends BaseResource
{
    /** The create form's choice to count the address as verified without its link */
    public const string MARK_EMAIL_VERIFIED_FIELD = 'mark_email_verified';

    /** Values of the «Con roles inactivos» filter: any reason, not a member, two-factor off (core#772) */
    public const string INACTIVE_ANY = 'any';

    public const string INACTIVE_MEMBERSHIP = 'membership';

    public const string INACTIVE_TWO_FACTOR = 'two_factor';

    protected static ?string $model = UserModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string
    {
        return PanelNavigationGroup::People->getLabel();
    }

    public static function getModelLabel(): string
    {
        return __('usuario');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Usuarios');
    }

    public static function form(Schema $schema): Schema
    {
        $baseRoleExists = RoleModel::query()->where('name', BaseRoleRule::NAME)->exists();
        // Read once per form, on first use: every option asks for it
        $grantableRoleNames = null;

        return $schema
            ->components([
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
                    ->unique(ignoreRecord: true)
                    // EditUser never writes the address: a new one waits for the link sent to it
                    ->helperText(static fn (string $operation, ?UserModel $record): ?string => match (true) {
                        $operation !== 'edit' => null,
                        filled($record?->pending_email) => (string) __('filament.users.fields.emailPendingHelp', ['email' => $record->pending_email]),
                        default => (string) __('filament.users.fields.emailChangeHelp'),
                    }),
                // Only when creating: an edited address is verified through its link, never by hand
                Checkbox::make(self::MARK_EMAIL_VERIFIED_FIELD)
                    ->label(__('filament.users.fields.markEmailVerified'))
                    ->helperText(__('filament.users.fields.markEmailVerifiedHelp'))
                    ->default(false)
                    ->visibleOn('create'),
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
                            throw new RuntimeException('Failed to read uploaded file');
                        }

                        $optimizedContents = $imageOptimizer->optimize(
                            $contents,
                            $file->getMimeType(),
                            $avatarSettings
                        );

                        // Named after the stored bytes; animated avatars are marked for animated delivery
                        $path = app(AvatarPath::class)->forUpload('avatars/'.now()->format('Y/m'), $optimizedContents);

                        Storage::disk('images')->put($path, $optimizedContents);

                        return $path;
                    })
                    ->nullable(),
                Section::make(__('filament.users.sections.roles'))
                    ->description(__('filament.users.sections.rolesDescription'))
                    ->schema([
                        CheckboxList::make('roles')
                            ->label(__('filament.users.fields.roles'))
                            // Core#772: roles that require two-factor authentication are paused
                            ->hint(static fn (?UserModel $record): ?string => self::pausedByTwoFactor($record)
                                ? (string) __('filament.users.roleBadges.withoutTwoFactor')
                                : null)
                            ->hintIcon(static fn (?UserModel $record): ?string => self::pausedByTwoFactor($record) ? 'heroicon-o-shield-exclamation' : null)
                            ->hintColor('warning')
                            // Only where the base role is ticked: never on an anonymized account
                            ->helperText(static fn (?UserModel $record): ?string => $baseRoleExists && $record?->anonymized_at === null
                                ? (string) __('filament.users.fields.rolesHelp')
                                : null)
                            // Not a relationship: EditUser and CreateUser save the role names
                            // through the grant rule and the authorization service
                            ->options(static fn (): array => RoleModel::query()
                                ->orderBy('display_name')
                                ->pluck('display_name', 'name')
                                ->all())
                            ->columns(2)
                            ->searchable()
                            ->bulkToggleable()
                            // Without users.assign_roles the list is read-only, and the pages
                            // ignore whatever roles are submitted
                            ->disabled(static fn (): bool => ! self::actorCanAssignRoles())
                            ->dehydrated()
                            // The base role of every account is always ticked and never unticked
                            // ("Deselect all" skips disabled options); a role the actor cannot
                            // grant cannot be ticked or unticked either. The pages enforce both
                            // on the server should a forged state change them
                            ->disableOptionWhen(static function (string $value) use (&$grantableRoleNames): bool {
                                $grantableRoleNames ??= self::grantableRoleNames();

                                return $value === BaseRoleRule::NAME || ! in_array($value, $grantableRoleNames, true);
                            })
                            // Filament only accepts enabled options by default, which would reject
                            // the ticked base role: validate against every role instead
                            ->in(static fn (CheckboxList $component): array => array_keys($component->getOptions()))
                            ->afterStateHydrated(static function (CheckboxList $component, ?array $state, ?UserModel $record) use ($baseRoleExists): void {
                                // Anonymized accounts hold no roles on purpose
                                if (! $baseRoleExists || $record?->anonymized_at !== null) {
                                    return;
                                }

                                $component->state(array_values(array_unique([...$state ?? [], BaseRoleRule::NAME])));
                            })
                            ->descriptions(static fn (?UserModel $record): array => self::roleDescriptions($record)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        // The ids with two-factor authentication on among those the page lists: one query for all
        // the rows, through TwoFactorHolders (core#772), so a reader that fails shows it off and
        // the roles that require it paused, like the access, instead of breaking the table
        $twoFactorOn = null;
        $hasTwoFactor = static function (UserModel $record, HasTable $livewire) use (&$twoFactorOn): bool {
            $twoFactorOn ??= app(TwoFactorHolders::class)->among(self::pageUserIds($livewire));

            return isset($twoFactorOn[strtolower((string) $record->getKey())]);
        };

        // Read once per table render, on first use: every row asks for it
        $associationRole = false;
        $inactiveRoles = static function (UserModel $record, HasTable $livewire) use (&$associationRole, $hasTwoFactor): array {
            if ($associationRole === false) {
                $associationRole = app(AssociationMembershipServiceInterface::class)->roleName();
            }

            return self::inactiveRoles($record, $associationRole, static fn (): bool => $hasTwoFactor($record, $livewire));
        };

        return $table
            ->columns([
                ImageColumn::make('avatar_public_id')
                    ->label(__('Avatar'))
                    ->circular()
                    ->disk('images')
                    ->visibleFrom('md'),
                TextColumn::make('name')
                    ->label(__('Nombre de usuario'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('display_name')
                    ->label(__('Nombre para mostrar'))
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('email')
                    ->label(__('Correo electrónico'))
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('sm'),
                TextColumn::make('roles.display_name')
                    ->label(__('filament.users.fields.roles'))
                    ->badge()
                    ->separator(', ')
                    ->color(fn (string $state, UserModel $record, HasTable $livewire): string => match (true) {
                        array_key_exists($state, $inactiveRoles($record, $livewire)) => 'gray',
                        $state === 'Administrator' => 'danger',
                        $state === 'Editor' => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (UserModel $record, HasTable $livewire): ?string => $inactiveRoles($record, $livewire) === []
                        ? null
                        : implode(' · ', array_unique($inactiveRoles($record, $livewire))))
                    ->visibleFrom('xl'),
                IconColumn::make('email_verified_at')
                    ->label(__('filament.users.fields.emailVerified'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn (UserModel $record): bool => $record->email_verified_at !== null)
                    ->visibleFrom('xl'),
                // Core#771: a pending setup does not count, only a confirmed one
                IconColumn::make('two_factor')
                    ->label(__('filament.users.fields.twoFactor'))
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing($hasTwoFactor)
                    // With the sidebar open, 1024 px leaves about 640: from `xl`, like the verified-email column
                    ->visibleFrom('xl'),
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
                // With the reason (core#772): not a member, or two-factor authentication off
                SelectFilter::make('inactive_roles')
                    ->label(__('filament.users.filters.inactiveRoles'))
                    ->options([
                        self::INACTIVE_ANY => __('filament.users.filters.inactiveReasons.any'),
                        self::INACTIVE_MEMBERSHIP => __('filament.users.filters.inactiveReasons.membership'),
                        self::INACTIVE_TWO_FACTOR => __('filament.users.filters.inactiveReasons.two_factor'),
                    ])
                    ->query(static fn (Builder $query, array $data): Builder => self::filterInactiveRoles($query, $data['value'] ?? null)),
                TernaryFilter::make('two_factor')
                    ->label(__('filament.users.filters.twoFactor.label'))
                    ->trueLabel(__('filament.users.filters.twoFactor.true'))
                    ->falseLabel(__('filament.users.filters.twoFactor.false'))
                    ->queries(
                        true: static fn (Builder $query): Builder => $query->whereIn('users.id', self::confirmedTwoFactorUserIds()),
                        false: static fn (Builder $query): Builder => $query->whereNotIn('users.id', self::confirmedTwoFactorUserIds()),
                        blank: static fn (Builder $query): Builder => $query,
                    ),
                TrashedFilter::make()
                    ->label(__('filament.users.filters.status'))
                    ->default('with'),
            ])
            // One menu: «Editar» beside the menu made the table overflow its container
            ->recordActions([
                ActionGroup::make([
                    RowDetailsAction::make(),
                    EditAction::make(),
                    Action::make('sendVerificationEmail')
                        ->authorize('update')
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
                        ->authorize('update')
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
                    ImpersonateAction::make(),
                    Action::make('deactivate')
                        ->authorize('delete')
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
                        ->successNotificationTitle(__('filament.users.actions.restored'))
                        ->after(fn (UserModel $record) => self::dropRolesOverLimitAfterRestore($record)),
                    Action::make('anonymize')
                        ->authorize('delete')
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
                        ->schema(function (UserModel $record): array {
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

    public static function getRelations(): array
    {
        return [
            RoleAuditRelationManager::class,
            ImpersonationsAsTargetRelationManager::class,
            ImpersonationsAsImpersonatorRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * A restored account comes back without the roles that filled up while it was away
     * (holder limit); the person restoring it is told which ones.
     */
    public static function dropRolesOverLimitAfterRestore(UserModel $record): void
    {
        $dropped = app(RoleHolderLimitGuard::class)->dropRolesOverLimit($record);

        if ($dropped === []) {
            return;
        }

        Notification::make()
            ->title(__('filament.users.actions.restoredWithoutRoles', ['roles' => implode(', ', $dropped)]))
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'display_name', 'email'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return $record instanceof UserModel ? [__('Correo electrónico') => $record->email] : [];
    }

    /**
     * @return Builder<UserModel>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<UserModel> $query */
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        return $query;
    }

    /**
     * Description of every role option: the role's own text plus its badges, and for an
     * existing account that is not a member, whether the role is inactive for it.
     *
     * @return array<string, string>
     */
    private static function roleDescriptions(?UserModel $record): array
    {
        $associationRole = app(AssociationMembershipServiceInterface::class)->roleName();
        // An anonymized account holds no roles on purpose: nothing is inactive for it
        $showInactive = $record instanceof UserModel && $record->anonymized_at === null;
        $isMember = $record instanceof UserModel
            && $associationRole !== null
            && $record->roles()->where('name', $associationRole)->exists();
        $descriptions = [];

        $lacksTwoFactor = $record instanceof UserModel && $showInactive
            && ! app(TwoFactorHolders::class)->has((string) $record->getKey());

        foreach (RoleModel::query()->get(['name', 'description', 'requires_association_member', 'max_holders', 'requires_two_factor']) as $role) {
            $parts = $role->description === null || $role->description === '' ? [] : [$role->description];

            if ($role->requires_association_member) {
                $parts[] = (string) __('filament.users.roleBadges.requiresMember');
            }

            if ($role->requires_two_factor) {
                $parts[] = (string) __('filament.users.roleBadges.requiresTwoFactor');
            }

            if ($role->max_holders !== null) {
                $parts[] = (string) __('filament.users.roleBadges.maxHolders', ['n' => $role->max_holders]);
            }

            if ($role->requires_association_member && $showInactive && ! $isMember) {
                $parts[] = (string) __('filament.users.roleBadges.inactive');
            }

            if ($role->requires_two_factor && $lacksTwoFactor) {
                $parts[] = (string) __('filament.users.roleBadges.pausedTwoFactor');
            }

            if ($parts !== []) {
                $descriptions[$role->name] = implode(' · ', $parts);
            }
        }

        return $descriptions;
    }

    /**
     * The ids of the accounts the table page lists, so a column can ask once for all its rows.
     *
     * @return list<string>
     */
    private static function pageUserIds(HasTable $livewire): array
    {
        $records = $livewire->getTableRecords();
        $items = $records instanceof Collection ? $records->all() : $records->items();

        return array_values(array_map(
            static fn (mixed $record): string => $record instanceof Model ? (string) $record->getKey() : '',
            $items,
        ));
    }

    /**
     * The accounts whose two-factor authentication is on: a confirmed setup only, never one still
     * being set up (core#771).
     *
     * @return Builder<UserTwoFactorModel>
     */
    private static function confirmedTwoFactorUserIds(): Builder
    {
        return UserTwoFactorModel::query()->select('user_id')->whereNotNull('confirmed_at');
    }

    /**
     * The roles that do nothing for this account, display name => why: it is not a member, or
     * (core#772) it has two-factor authentication off. Two-factor is asked only when a role
     * requires it.
     *
     * @param  callable(): bool  $hasTwoFactor
     * @return array<string, string>
     */
    private static function inactiveRoles(UserModel $record, ?string $associationRole, callable $hasTwoFactor): array
    {
        if ($record->anonymized_at !== null) {
            return [];
        }

        $roles = $record->roles;
        $isMember = $associationRole !== null && $roles->contains('name', $associationRole);
        $lacksTwoFactor = null;
        $inactive = [];

        foreach ($roles as $role) {
            if ($role->requires_association_member && ! $isMember) {
                $inactive[$role->display_name] = (string) __('filament.users.roleBadges.inactive');
            } elseif ($role->requires_two_factor && ($lacksTwoFactor ??= ! $hasTwoFactor())) {
                $inactive[$role->display_name] = (string) __('filament.users.roleBadges.pausedTwoFactor');
            }
        }

        return $inactive;
    }

    /**
     * Whether the account holds a role that requires two-factor authentication while it has it
     * off (core#772): the form shows the «Sin doble factor» badge.
     */
    private static function pausedByTwoFactor(?UserModel $record): bool
    {
        return $record instanceof UserModel
            && $record->anonymized_at === null
            && $record->roles()->where('roles.requires_two_factor', true)->exists()
            && ! app(TwoFactorHolders::class)->has((string) $record->getKey());
    }

    /**
     * @param  Builder<UserModel>  $query
     * @return Builder<UserModel>
     */
    private static function filterInactiveRoles(Builder $query, mixed $reason): Builder
    {
        if (! in_array($reason, [self::INACTIVE_ANY, self::INACTIVE_MEMBERSHIP, self::INACTIVE_TWO_FACTOR], true)) {
            return $query;
        }

        $holders = app(InactiveRoleHolders::class);
        $notMembers = static fn (): Builder => $holders
            ->query(app(AssociationMembershipServiceInterface::class)->roleName())
            ->select('users.id');

        return match ($reason) {
            self::INACTIVE_MEMBERSHIP => $query->whereIn('users.id', $notMembers()),
            self::INACTIVE_TWO_FACTOR => $query->whereIn('users.id', $holders->withoutTwoFactorIds()),
            default => $query->where(static fn (Builder $inner): Builder => $inner
                ->whereIn('users.id', $notMembers())
                ->orWhereIn('users.id', $holders->withoutTwoFactorIds())),
        };
    }

    private static function actorCanAssignRoles(): bool
    {
        $actor = Auth::user();

        return $actor !== null
            && app(AuthorizationServiceInterface::class)->can($actor, RoleGrantPolicy::ASSIGN_ROLES_PERMISSION);
    }

    /**
     * @return list<string>
     */
    private static function grantableRoleNames(): array
    {
        $actor = Auth::user();

        return $actor === null ? [] : app(RoleGrantPolicy::class)->grantableRoleNames($actor);
    }
}
