<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Application\Authorization\Services\AssociationMemberRoleRule;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\BaseRoleRule;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\Authorization\Services\RoleDefinitionChanges;
use App\Application\Authorization\Services\RoleRestrictionRule;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Domain\Authorization\ValueObjects\RoleName;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\RoleResource\RelationManagers\RoleAuditRelationManager;
use App\Filament\Resources\RoleResource\RoleRequirementImpact;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class RoleResource extends BaseResource
{
    protected static ?string $model = RoleModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('filament.roles.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.roles.pluralLabel');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.roles.sections.general'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament.roles.fields.name'))
                            ->required()
                            ->maxLength(50)
                            ->rule(static fn (?RoleModel $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                // A legacy name that is kept as it is must not block other edits
                                if ($record instanceof RoleModel && $value === $record->name) {
                                    return;
                                }

                                if (! is_string($value) || preg_match(RoleName::PATTERN, $value) !== 1) {
                                    $fail((string) __('filament.roles.fields.nameFormat'));
                                }
                            })
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?RoleModel $record): bool => $record instanceof RoleModel && self::isLocked($record))
                            ->hint(fn (?RoleModel $record): ?string => self::lockedNameHint($record))
                            ->hintIcon(fn (?RoleModel $record): ?string => self::lockedNameHint($record) !== null ? 'heroicon-o-lock-closed' : null)
                            ->hintColor('warning')
                            ->helperText(__('filament.roles.fields.nameHelp')),
                        TextInput::make('display_name')
                            ->label(__('filament.roles.fields.displayName'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('filament.roles.fields.description'))
                            ->rows(3)
                            ->maxLength(500),
                        Toggle::make('requires_association_member')
                            ->label(__('filament.roles.fields.requiresAssociationMember'))
                            ->live()
                            ->disabled(fn (?RoleModel $record): bool => ! self::canBeRestricted($record))
                            ->hint(fn (?RoleModel $record): ?string => self::canBeRestricted($record) ? null : (string) __('filament.roles.fields.restrictionLockedHint'))
                            ->hintIcon(fn (?RoleModel $record): ?string => self::canBeRestricted($record) ? null : 'heroicon-o-lock-closed')
                            ->hintColor('warning')
                            ->helperText(fn (Get $get, ?RoleModel $record): string => self::requirementHelp((bool) $get('requires_association_member'), $record)),
                        TextInput::make('max_holders')
                            ->label(__('filament.roles.fields.maxHolders'))
                            ->integer()
                            ->minValue(1)
                            ->nullable()
                            ->dehydrateStateUsing(static fn (mixed $state): ?int => blank($state) ? null : (int) $state)
                            ->disabled(fn (?RoleModel $record): bool => ! self::canBeRestricted($record))
                            ->helperText(fn (?RoleModel $record): string => self::maxHoldersHelp($record)),
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
                            ->descriptions(fn (?RoleModel $record): array => self::permissionDescriptions($record)),
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
                IconColumn::make('requires_association_member')
                    ->label(__('filament.roles.fields.requiresAssociationMemberShort'))
                    ->boolean(),
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
            ->recordActions([
                EditAction::make(),
                self::deleteAction()
                    ->hidden(fn (RoleModel $record): bool => self::isLocked($record)),
            ]);
    }

    /**
     * Deleting a role from the panel (table row and edit page): the role and its history
     * entries (the deletion, and the role taken from each holder) go in one transaction.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(static fn (RoleModel $record): bool => DB::transaction(static function () use ($record): bool {
                app(RoleAuditLoggerInterface::class)->roleChanged(
                    $record->id,
                    $record->name,
                    RoleAuditEvent::RoleDeleted,
                    app(RoleDefinitionChanges::class)->between(
                        array_values($record->permissions()->pluck('permissions.key')->map(strval(...))->all()),
                        [],
                    ),
                );

                return (bool) $record->delete();
            }))
            ->after(static fn () => app(AuthorizationServiceInterface::class)->invalidateCache());
    }

    /**
     * Whether the role may carry the association member requirement and a holder cap. A role
     * that is not saved yet can always be (the name it gets is checked when saving).
     */
    public static function canBeRestricted(?RoleModel $record): bool
    {
        return ! $record instanceof RoleModel
            || app(RoleRestrictionRule::class)->canBeRestricted($record->name);
    }

    private static function requirementHelp(bool $requires, ?RoleModel $record): string
    {
        $help = (string) __('filament.roles.fields.requiresAssociationMemberHelp');

        if (! $requires || ! $record instanceof RoleModel || $record->requires_association_member) {
            return $help;
        }

        $count = app(RoleRequirementImpact::class)->inactiveHolderCount($record);

        return $count > 0
            ? $help.' '.__('filament.roles.fields.requirementImpact', ['count' => $count])
            : $help;
    }

    private static function maxHoldersHelp(?RoleModel $record): string
    {
        $help = (string) __('filament.roles.fields.maxHoldersHelp');

        if (! $record instanceof RoleModel || $record->max_holders !== 1) {
            return $help;
        }

        $holder = $record->users()->orderBy('users.name')->first();

        return $holder instanceof UserModel
            ? $help.' '.__('filament.roles.fields.maxHoldersCurrent', ['name' => $holder->name])
            : $help;
    }

    /**
     * Protected roles, the base role of every account and the association member role can
     * be neither deleted nor renamed.
     */
    public static function isLocked(RoleModel $record): bool
    {
        return $record->is_protected || self::isBaseRole($record) || self::isAssociationMemberRole($record);
    }

    /**
     * Whether the role is "member", the base role of every account: deleting or renaming it
     * would strip every account of it at once.
     */
    public static function isBaseRole(?RoleModel $record): bool
    {
        return $record instanceof RoleModel && app(BaseRoleRule::class)->isBaseRoleName($record->name);
    }

    /**
     * Why the name of a role that is not protected cannot be changed, if it cannot.
     */
    /**
     * Each permission's key, marked when the core or its module suggests it for this role.
     * Only marked: a suggestion is never granted without an admin ticking it.
     *
     * @return array<string, string>
     */
    private static function permissionDescriptions(?RoleModel $record): array
    {
        $registry = app(PermissionRegistryInterface::class);
        $suggested = (string) __('filament.roles.fields.suggestedForRole');

        return PermissionModel::query()
            ->pluck('key', 'id')
            ->map(static fn (string $key): string => $record instanceof RoleModel
                && in_array($record->name, $registry->find($key)->defaultRoles ?? [], true)
                ? "{$key} · {$suggested}"
                : $key)
            ->all();
    }

    private static function lockedNameHint(?RoleModel $record): ?string
    {
        return match (true) {
            self::isBaseRole($record) => (string) __('filament.roles.fields.baseRoleHint'),
            self::isAssociationMemberRole($record) => (string) __('filament.roles.fields.associationMemberRoleHint'),
            default => null,
        };
    }

    /**
     * Whether the role is the association member role set in the site settings: modules
     * find association members by its name, so it can be neither renamed nor deleted.
     */
    public static function isAssociationMemberRole(?RoleModel $record): bool
    {
        return $record instanceof RoleModel
            && app(AssociationMemberRoleRule::class)->isAssociationMemberRoleName($record->name);
    }

    public static function getRelations(): array
    {
        return [
            RoleAuditRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
