<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Application\Authorization\Services\AssociationMemberRoleRule;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\BaseRoleRule;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\Authorization\Services\RoleDefinitionChanges;
use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Application\Authorization\Services\RoleRestrictionRule;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Domain\Authorization\ValueObjects\RoleName;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\RoleResource\PermissionCatalog;
use App\Filament\Resources\RoleResource\PermissionOption;
use App\Filament\Resources\RoleResource\PermissionSection;
use App\Filament\Resources\RoleResource\PermissionTab;
use App\Filament\Resources\RoleResource\RelationManagers\RoleAuditRelationManager;
use App\Filament\Resources\RoleResource\RoleRequirementImpact;
use App\Infrastructure\Authorization\Services\RoleHolderLimitGuard;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use WeakMap;

class RoleResource extends BaseResource
{
    protected static ?string $model = RoleModel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 2;

    /** @var WeakMap<object, list<PermissionTab>>|null */
    private static ?WeakMap $permissionTabsByPage = null;

    /** @var WeakMap<object, int>|null */
    private static ?WeakMap $holderCountByPage = null;

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
        $livewire = $schema->getLivewire();
        $permissionTabs = self::permissionTabs($livewire);
        $editable = self::editablePermissionKeys();

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
                            ->live(onBlur: true)
                            ->disabled(fn (?RoleModel $record): bool => ! self::canBeRestricted($record))
                            ->hint(fn (Get $get, ?RoleModel $record): ?string => self::maxHoldersWarning($livewire, $get('max_holders'), $record))
                            ->hintIcon(fn (Get $get, ?RoleModel $record): ?string => self::maxHoldersWarning($livewire, $get('max_holders'), $record) !== null ? 'heroicon-o-exclamation-triangle' : null)
                            ->hintColor('warning')
                            ->helperText(fn (?RoleModel $record): string => self::maxHoldersHelp($record)),
                        Toggle::make('is_protected')
                            ->label(__('filament.roles.fields.isProtected'))
                            ->helperText(__('filament.roles.fields.isProtectedHelp'))
                            ->disabled()
                            ->dehydrated(false),
                        // admin.access, shown as a switch instead of a box in the tabs. The pages
                        // turn it into the permission; the server check stays authoritative.
                        Toggle::make('panel_access')
                            ->label(__('filament.roles.fields.panelAccess'))
                            ->helperText(__('filament.roles.fields.panelAccessHelp'))
                            ->live()
                            // On those roles it can be turned off (a legacy admin.access), never on
                            ->disabled(fn (Get $get, ?RoleModel $record): bool => (self::isPanelAccessLocked($record) && ! (bool) $get('panel_access'))
                                || ($editable !== null && ! in_array('admin.access', $editable, true)))
                            ->hint(fn (Get $get, ?RoleModel $record): ?string => self::panelAccessHint((bool) $get('panel_access'), $record))
                            ->hintIcon(fn (?RoleModel $record): ?string => self::isPanelAccessLocked($record) ? 'heroicon-o-lock-closed' : null)
                            ->hintColor('warning'),
                        Callout::make(fn (Get $get): string => trans_choice(
                            'filament.roles.fields.permissionsWithoutPanel',
                            self::tickedCount($get, $permissionTabs),
                            ['count' => self::tickedCount($get, $permissionTabs)],
                        ))
                            ->warning()
                            // The base and association roles never enter the panel: their
                            // permissions are for the public site
                            ->visible(fn (Get $get, ?RoleModel $record): bool => ! self::isPanelAccessLocked($record)
                                && ! (bool) $get('panel_access')
                                && self::tickedCount($get, $permissionTabs) > 0),
                    ]),
                Section::make(__('filament.roles.sections.permissions'))
                    ->description(__('filament.roles.sections.permissionsDescription'))
                    ->schema([
                        // Filters the tabs in the browser only: it holds no form state
                        View::make('filament.resources.roles.permission-search')
                            ->viewData(['index' => self::permissionSearchIndex($permissionTabs, $schema->getRecord())])
                            ->hidden($permissionTabs === []),
                        Tabs::make(__('filament.roles.fields.permissions'))
                            ->tabs(array_map(
                                static fn (PermissionTab $tab): Tab => self::permissionTab($tab, $editable),
                                $permissionTabs,
                            ))
                            ->activeTab(fn (Get $get): int => self::firstTabWithPermissions($get, $permissionTabs))
                            ->extraAttributes(['data-permission-tabs' => true])
                            ->hidden($permissionTabs === []),
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
            ? $help.' '.trans_choice('filament.roles.fields.requirementImpact', $count, ['count' => $count])
            : $help;
    }

    private static function maxHoldersHelp(?RoleModel $record): string
    {
        $help = (string) __('filament.roles.fields.maxHoldersHelp');

        if (! $record instanceof RoleModel || $record->max_holders !== 1) {
            return $help;
        }

        $holder = $record->users()->whereNull('users.anonymized_at')->orderBy('users.name')->first();

        return $holder instanceof UserModel
            ? $help.' '.__('filament.roles.fields.maxHoldersCurrent', ['name' => $holder->display_name ?? $holder->name])
            : $help;
    }

    /**
     * Lowering the limit below the current holders removes nobody, it only stops new ones:
     * the form says so while the typed limit is below the count.
     */
    private static function maxHoldersWarning(object $livewire, mixed $maxHolders, ?RoleModel $record): ?string
    {
        if (! $record instanceof RoleModel || ! self::canBeRestricted($record)) {
            return null;
        }

        $max = filter_var($maxHolders, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($max === false) {
            return null;
        }

        // Counted once per request: the hint and its icon both ask
        self::$holderCountByPage ??= new WeakMap;
        $count = self::$holderCountByPage[$livewire] ??= app(RoleHolderLimitGuard::class)->holderCount($record);

        return $count > $max
            ? trans_choice('filament.roles.fields.maxHoldersOverLimit', $count, ['count' => $count, 'max' => $max])
            : null;
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
     * admin.access is refused on the base role of every account and on the association member
     * role, so the panel access switch is locked there.
     */
    private static function isPanelAccessLocked(?RoleModel $record): bool
    {
        return self::isBaseRole($record) || self::isAssociationMemberRole($record);
    }

    private static function panelAccessHint(bool $panelAccess, ?RoleModel $record): ?string
    {
        if (! self::isPanelAccessLocked($record)) {
            return null;
        }

        return $panelAccess
            ? (string) __('filament.roles.fields.panelAccessLegacyHint')
            : (string) __('filament.roles.fields.panelAccessLockedHint');
    }

    /**
     * The permission keys the signed-in user may tick or untick in the role editor (null: all of
     * them). Without a signed-in user, none.
     *
     * @return list<string>|null
     */
    public static function editablePermissionKeys(): ?array
    {
        $actor = auth()->user();

        return $actor !== null ? app(RoleGrantPolicy::class)->editablePermissionKeys($actor) : [];
    }

    /**
     * The catalog tabs that have something to tick, built once per page instance (one Livewire
     * request): the form, its live updates and the save all read the same tabs.
     *
     * @return list<PermissionTab>
     */
    public static function permissionTabs(object $livewire): array
    {
        self::$permissionTabsByPage ??= new WeakMap;

        return self::$permissionTabsByPage[$livewire] ??= array_values(array_filter(
            app(PermissionCatalog::class)->tabs(),
            static fn (PermissionTab $tab): bool => $tab->sections !== [],
        ));
    }

    /**
     * @param  list<string>|null  $editable
     */
    private static function permissionTab(PermissionTab $tab, ?array $editable): Tab
    {
        $total = array_sum(array_map(static fn (PermissionSection $section): int => count($section->permissions), $tab->sections));

        return Tab::make($tab->label)
            // Stable keys: two modules may share a display name
            ->key('permission-tab-'.$tab->key, isInheritable: false)
            ->badge(static fn (Get $get): string => self::tickedCount($get, [$tab]).'/'.$total)
            ->schema(array_map(
                static fn (PermissionSection $section): Fieldset => self::permissionSection($tab, $section, $editable),
                $tab->sections,
            ));
    }

    /**
     * @param  list<string>|null  $editable
     */
    private static function permissionSection(PermissionTab $tab, PermissionSection $section, ?array $editable): Fieldset
    {
        $options = [];
        $keys = [];
        foreach ($section->permissions as $option) {
            $options[$option->id] = $option->label;
            $keys[$option->id] = $option->key;
        }

        return Fieldset::make($section->label)
            ->extraAttributes(['data-permission-section' => "{$tab->key}.{$section->key}"])
            ->columns(1)
            ->schema([
                CheckboxList::make("permissions_by_section.{$tab->key}.{$section->key}")
                    ->label($section->label)
                    ->hiddenLabel()
                    ->options($options)
                    ->descriptions(static fn (?RoleModel $record): array => self::permissionDescriptions($section, $record))
                    // What the actor does not hold stays as it is: the pages keep it when saving
                    ->disableOptionWhen(static fn (mixed $value): bool => $editable !== null
                        && ! in_array($keys[is_scalar($value) ? (string) $value : ''] ?? '', $editable, true))
                    // Filament only accepts enabled options by default, which would reject a
                    // ticked disabled box: the server check decides on those
                    ->in(static fn (CheckboxList $component): array => array_keys($component->getOptions()))
                    // Filament labels the toggle from "all boxes ticked", which a locked box never is:
                    // with one in the section the button would alternately untick what the actor holds
                    ->bulkToggleable($editable === null || array_diff($keys, $editable) === [])
                    ->selectAllAction(static fn (Action $action): Action => $action->label(__('filament.roles.fields.selectAllPermissions')))
                    ->deselectAllAction(static fn (Action $action): Action => $action->label(__('filament.roles.fields.deselectAllPermissions')))
                    ->columns(2)
                    ->gridDirection('row')
                    // The tab badges and the panel access warning follow the boxes
                    ->live(),
            ]);
    }

    /**
     * Each permission's description, marked when the core or its module suggests it for this
     * role. Only marked: a suggestion is never granted without an admin ticking it.
     *
     * @return array<string, string>
     */
    private static function permissionDescriptions(PermissionSection $section, ?RoleModel $record): array
    {
        $suggested = (string) __('filament.roles.fields.suggestedForRole');
        $descriptions = [];

        foreach ($section->permissions as $option) {
            $parts = array_filter([
                $option->description,
                $record instanceof RoleModel && in_array($record->name, $option->suggestedFor, true) ? $suggested : null,
            ], filled(...));

            if ($parts !== []) {
                $descriptions[$option->id] = implode(' · ', $parts);
            }
        }

        return $descriptions;
    }

    /**
     * What the permission search matches: every option of every tab, with the text shown for it
     * (label and description). The keys are the hooks the search finds in the page: the tab
     * buttons, the sections and the boxes' values.
     *
     * @param  list<PermissionTab>  $tabs
     * @return list<array{tab: string, sections: list<array{key: string, options: list<array{id: string, text: string}>}>}>
     */
    private static function permissionSearchIndex(array $tabs, mixed $record): array
    {
        $record = $record instanceof RoleModel ? $record : null;

        return array_map(static fn (PermissionTab $tab): array => [
            'tab' => 'permission-tab-'.$tab->key,
            'sections' => array_map(static function (PermissionSection $section) use ($tab, $record): array {
                $descriptions = self::permissionDescriptions($section, $record);

                return [
                    'key' => "{$tab->key}.{$section->key}",
                    'options' => array_map(static fn (PermissionOption $option): array => [
                        'id' => $option->id,
                        'text' => trim($option->label.' '.($descriptions[$option->id] ?? '')),
                    ], $section->permissions),
                ];
            }, $tab->sections),
        ], $tabs);
    }

    /**
     * How many permissions of the given tabs are ticked in the form state.
     *
     * @param  list<PermissionTab>  $tabs
     */
    private static function tickedCount(Get $get, array $tabs): int
    {
        $count = 0;

        foreach ($tabs as $tab) {
            foreach ($tab->sections as $section) {
                $ticked = $get("permissions_by_section.{$tab->key}.{$section->key}");
                $ticked = is_array($ticked) ? array_map(strval(...), array_filter($ticked, is_scalar(...))) : [];
                $ids = array_map(static fn (PermissionOption $option): string => $option->id, $section->permissions);

                $count += count(array_intersect($ticked, $ids));
            }
        }

        return $count;
    }

    /**
     * The tab to open: the first one with something ticked (1-based, as Filament counts them),
     * or the first one. Read from the form state, which the page fills from the role: only the
     * first render matters, the browser keeps the open tab afterwards.
     *
     * @param  list<PermissionTab>  $tabs
     */
    private static function firstTabWithPermissions(Get $get, array $tabs): int
    {
        foreach ($tabs as $index => $tab) {
            if (self::tickedCount($get, [$tab]) > 0) {
                return $index + 1;
            }
        }

        return 1;
    }

    /**
     * Why the name of a role that is not protected cannot be changed, if it cannot.
     */
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
