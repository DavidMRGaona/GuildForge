<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\PermissionCatalog;
use App\Filament\Resources\RoleResource\PermissionTab;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Tabs;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Authorization\FillsRolePermissionTabs;
use Tests\TestCase;

/**
 * The role editor groups permissions in tabs (one per area and enabled module) and sections
 * (one per resource), shows labels and descriptions instead of keys, and turns admin.access
 * into the panel access switch. Saving syncs the union of every tab.
 */
final class RolePermissionTabsTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use FillsRolePermissionTabs;
    use LazilyRefreshDatabase;

    private const string MODULE_KEY = 'event-registrations:registrations.view_any';

    private const string MODULE_TAB = 'module-event-registrations';

    protected function setUp(): void
    {
        parent::setUp();

        $registry = app(PermissionRegistryInterface::class);
        $registry->register(new PermissionDefinitionDTO(
            key: self::MODULE_KEY,
            label: 'Ver inscripciones',
            resource: 'registrations',
            action: 'view_any',
            module: 'event-registrations',
            defaultRoles: ['editor'],
            description: 'Consultar quién se ha inscrito',
        ));
        $registry->register(new PermissionDefinitionDTO(
            key: 'event-registrations:registrations.manage',
            label: 'Gestionar inscripciones',
            resource: 'registrations',
            action: 'manage',
            module: 'event-registrations',
            description: 'Aceptar y cancelar inscripciones',
        ));
        // A module that is installed but disabled: its permissions are in no tab
        $registry->register(new PermissionDefinitionDTO(
            key: 'old-module:things.view',
            label: 'Ver cosas',
            resource: 'things',
            action: 'view',
            module: 'old-module',
        ));
        $registry->syncToDatabase();

        $this->enableModules(['event-registrations' => 'Inscripciones']);
    }

    public function test_saving_across_tabs_syncs_the_union(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->role();

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions_by_section.content.events', [$this->id('events.view_any'), $this->id('events.create')])
            ->set('data.permissions_by_section.'.self::MODULE_TAB.'.registrations', [$this->id(self::MODULE_KEY)])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['events.view_any', 'events.create', self::MODULE_KEY], $this->keys($role));
    }

    public function test_the_form_is_filled_per_tab_and_section(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->role(['events.view_any', self::MODULE_KEY, 'admin.access']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSchemaStateSet([
                'panel_access' => true,
                'permissions_by_section.content.events' => [$this->id('events.view_any')],
                'permissions_by_section.content.articles' => [],
                'permissions_by_section.'.self::MODULE_TAB.'.registrations' => [$this->id(self::MODULE_KEY)],
            ]);
    }

    public function test_panel_access_toggle_maps_to_admin_access(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->role(['events.view_any']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSchemaStateSet(['panel_access' => false])
            ->set('data.panel_access', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['events.view_any', 'admin.access'], $this->keys($role));

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSchemaStateSet(['panel_access' => true])
            ->set('data.panel_access', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['events.view_any'], $this->keys($role));
    }

    public function test_panel_access_is_locked_on_the_base_and_association_roles(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        foreach (['member', 'socio'] as $name) {
            $role = RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);

            Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
                ->assertFormFieldIsDisabled('panel_access')
                // A forged state is still refused on the server
                ->set('data.panel_access', true)
                ->call('save')
                ->assertNotified();

            $this->assertNotContains('admin.access', $this->keys($role));
        }
    }

    public function test_a_legacy_panel_access_on_the_base_role_can_be_turned_off(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $member = RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Miembro']);
        // Given before the switch was locked on this role
        $member->permissions()->attach([$this->id('admin.access'), $this->id('events.view_any')]);

        Livewire::test(EditRole::class, ['record' => $member->getRouteKey()])
            ->assertSchemaStateSet(['panel_access' => true])
            ->assertFormFieldIsEnabled('panel_access')
            ->assertSee(__('filament.roles.fields.panelAccessLegacyHint'))
            ->set('data.panel_access', false)
            ->assertFormFieldIsDisabled('panel_access')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['events.view_any'], $this->keys($member));

        $entry = RoleAuditEntryModel::query()->where('role_id', $member->id)->where('event', 'role_updated')->firstOrFail();
        $this->assertSame(['admin.access'], $entry->changes['permissions_removed'] ?? null);
        $this->assertArrayNotHasKey('permissions_added', $entry->changes);
    }

    public function test_permissions_of_disabled_modules_survive_a_save(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->role(['events.view_any', 'old-module:things.view']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions_by_section.content.events', [$this->id('events.view_any'), $this->id('events.create')])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['events.view_any', 'events.create', 'old-module:things.view'], $this->keys($role));
    }

    public function test_key_is_not_rendered(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->role(['events.view_any', self::MODULE_KEY]);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSee('Ver inscripciones')
            ->assertSee(__('authorization.permissions.articles.view_any'))
            ->assertDontSee(self::MODULE_KEY)
            ->assertDontSee('articles.view_any')
            ->assertDontSee('admin.access');

        $this->get(EditRole::getUrl(['record' => $role]))
            ->assertOk()
            ->assertSee('Ver inscripciones')
            ->assertDontSee(self::MODULE_KEY)
            ->assertDontSee('articles.view_any');
    }

    public function test_tabs_show_ticked_over_total_and_open_on_the_first_with_something_ticked(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->role([self::MODULE_KEY]);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSee('1/2')
            ->assertSeeHtml('permission-tab-'.self::MODULE_TAB)
            ->set('data.permissions_by_section.'.self::MODULE_TAB.'.registrations', [
                $this->id(self::MODULE_KEY),
                $this->id('event-registrations:registrations.manage'),
            ])
            ->assertSee('2/2');

        $this->assertSame(
            $this->tabPosition(self::MODULE_TAB),
            $this->activeTab($role),
        );
        $this->assertSame(1, $this->activeTab($this->role()));
    }

    public function test_description_and_suggested_mark_are_rendered(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $editor = RoleModel::query()->firstOrCreate(['name' => 'editor'], ['display_name' => 'Editor']);

        Livewire::test(EditRole::class, ['record' => $editor->getRouteKey()])
            ->assertSee('Consultar quién se ha inscrito · Sugerido para este rol')
            ->assertSee('Aceptar y cancelar inscripciones')
            ->assertDontSee('Aceptar y cancelar inscripciones · Sugerido para este rol');

        Livewire::test(EditRole::class, ['record' => $this->role()->getRouteKey()])
            ->assertSee('Consultar quién se ha inscrito')
            ->assertDontSee('Consultar quién se ha inscrito · Sugerido para este rol');
    }

    public function test_warning_when_permissions_without_panel_access(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->role(['events.view_any']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSee('Este rol tiene 1 permiso del panel, pero no puede entrar en él.')
            ->set('data.permissions_by_section.content.events', [$this->id('events.view_any'), $this->id('events.create')])
            ->assertSee('Este rol tiene 2 permisos del panel, pero no puede entrar en él.')
            ->set('data.panel_access', true)
            ->assertDontSee('pero no puede entrar en él');

        // Nothing ticked: nothing to warn about
        Livewire::test(EditRole::class, ['record' => $this->role()->getRouteKey()])
            ->assertDontSee('pero no puede entrar en él');

        // The base role never enters the panel: its permissions are for the public site
        $member = RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Miembro']);
        $member->permissions()->attach($this->id('events.view_any'));
        Livewire::test(EditRole::class, ['record' => $member->getRouteKey()])
            ->assertDontSee('pero no puede entrar en él');
    }

    public function test_non_admin_sees_foreign_permissions_disabled_and_cannot_forge_them(): void
    {
        $actor = $this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.update', 'events.view_any', 'events.create']);
        $this->actingAs($actor);
        $role = $this->role(['events.view_any']);
        $forbidden = $this->id('users.delete');

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertFormFieldExists('permissions_by_section.users.users', 'form', fn (CheckboxList $field): bool => $field->isOptionDisabled($forbidden, 'x'))
            ->assertFormFieldExists('permissions_by_section.content.events', 'form', fn (CheckboxList $field): bool => ! $field->isOptionDisabled($this->id('events.create'), 'x'))
            ->set('data.permissions_by_section.users.users', [$forbidden])
            ->call('save')
            ->assertNotified();

        $this->assertSame(['events.view_any'], $this->keys($role));

        // What they hold can be ticked
        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions_by_section.content.events', [$this->id('events.view_any'), $this->id('events.create')])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['events.view_any', 'events.create'], $this->keys($role));
    }

    public function test_create_role_with_tabs(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $page = Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'inscripciones', 'display_name' => 'Inscripciones']);
        $this->tickPermissions($page, [$this->id('events.view_any'), $this->id(self::MODULE_KEY), $this->id('admin.access')])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = RoleModel::query()->where('name', 'inscripciones')->firstOrFail();
        $this->assertEqualsCanonicalizing(['events.view_any', self::MODULE_KEY, 'admin.access'], $this->keys($role));

        $entry = RoleAuditEntryModel::query()->where('role_id', $role->id)->firstOrFail();
        $this->assertSame('role_created', $entry->event);
        $this->assertEqualsCanonicalizing(['events.view_any', self::MODULE_KEY, 'admin.access'], $entry->changes['permissions_added'] ?? []);
    }

    /**
     * @param  list<string>  $keys
     */
    private function role(array $keys = []): RoleModel
    {
        $role = RoleModel::query()->create(['name' => 'role-'.uniqid(), 'display_name' => 'Role']);
        $role->permissions()->attach(array_map($this->id(...), $keys));

        return $role;
    }

    private function id(string $key): string
    {
        return (string) PermissionModel::query()->where('key', $key)->valueOrFail('id');
    }

    /**
     * @return list<string>
     */
    private function keys(RoleModel $role): array
    {
        return array_values(array_map(strval(...), $role->permissions()->pluck('permissions.key')->all()));
    }

    private function activeTab(RoleModel $role): int
    {
        $page = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])->instance();
        $this->assertInstanceOf(EditRole::class, $page);

        $tabs = $page->form->getComponent(static fn (mixed $component): bool => $component instanceof Tabs);
        $this->assertInstanceOf(Tabs::class, $tabs);

        return $tabs->getActiveTab();
    }

    private function tabPosition(string $tabKey): int
    {
        $tabs = array_values(array_filter(
            app(PermissionCatalog::class)->tabs(),
            static fn (PermissionTab $tab): bool => $tab->sections !== [],
        ));

        foreach ($tabs as $index => $tab) {
            if ($tab->key === $tabKey) {
                return $index + 1;
            }
        }

        $this->fail("Tab {$tabKey} not found");
    }

    /**
     * @param  array<string, string>  $modules  name => display name
     */
    private function enableModules(array $modules): void
    {
        $collection = new ModuleCollection;
        foreach ($modules as $name => $display) {
            $collection->add(new Module(
                id: ModuleId::generate(),
                name: new ModuleName($name),
                displayName: $display,
                description: '',
                version: ModuleVersion::fromString('1.0.0'),
                author: 'Test',
                requirements: new ModuleRequirements(null, null, [], []),
                status: ModuleStatus::Enabled,
            ));
        }

        $manager = $this->createStub(ModuleManagerServiceInterface::class);
        $manager->method('enabled')->willReturn($collection->enabled());
        $this->app->instance(ModuleManagerServiceInterface::class, $manager);
    }
}
