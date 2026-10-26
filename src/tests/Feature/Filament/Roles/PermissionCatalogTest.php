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
use App\Filament\Resources\RoleResource\PermissionCatalog;
use App\Filament\Resources\RoleResource\PermissionSection;
use App\Filament\Resources\RoleResource\PermissionTab;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class PermissionCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $registry = app(PermissionRegistryInterface::class);
        $registry->register(new PermissionDefinitionDTO('venuebookings:bookings.manage', 'Gestionar reservas', 'bookings', 'manage', 'venuebookings', ['editor'], 'Crear y editar reservas', 'Reservas'));
        $registry->register(new PermissionDefinitionDTO('venuebookings:rooms.view_any', 'Ver salas', 'rooms', 'view_any', 'venuebookings'));
        $registry->register(new PermissionDefinitionDTO('gametables:tables.view_any', 'Ver mesas', 'tables', 'view_any', 'gametables'));
        $registry->syncToDatabase();
    }

    /**
     * @param  array<string, string>  $modules  name => display name
     * @param  list<string>  $disabled
     */
    private function modules(array $modules, array $disabled = []): void
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
                status: in_array($name, $disabled, true) ? ModuleStatus::Disabled : ModuleStatus::Enabled,
            ));
        }

        $manager = $this->createStub(ModuleManagerServiceInterface::class);
        $manager->method('enabled')->willReturn($collection->enabled());
        $this->app->instance(ModuleManagerServiceInterface::class, $manager);
    }

    /** @return list<PermissionTab> */
    private function tabs(): array
    {
        return app(PermissionCatalog::class)->tabs();
    }

    /**
     * @param  list<PermissionTab>  $tabs
     * @return list<string>
     */
    private function keys(array $tabs): array
    {
        $keys = [];
        foreach ($tabs as $tab) {
            foreach ($tab->sections as $section) {
                foreach ($section->permissions as $option) {
                    $keys[] = $option->key;
                }
            }
        }

        return $keys;
    }

    /** @param list<PermissionTab> $tabs */
    private function tab(array $tabs, string $key): PermissionTab
    {
        foreach ($tabs as $tab) {
            if ($tab->key === $key) {
                return $tab;
            }
        }

        $this->fail("Tab {$key} not found");
    }

    /** @return list<string> */
    private function sectionKeys(PermissionTab $tab): array
    {
        return array_map(fn (PermissionSection $s): string => $s->key, $tab->sections);
    }

    private function register(string $key, string $label, string $resource, string $module, ?string $group = null, bool $inDatabase = true): void
    {
        [, $action] = explode('.', $key, 2);
        app(PermissionRegistryInterface::class)->register(new PermissionDefinitionDTO($key, $label, $resource, $action, $module, [], null, $group));
        if ($inDatabase) {
            PermissionModel::query()->create(['key' => $key, 'label' => $label, 'resource' => $resource, 'action' => $action, 'module' => $module]);
        }
    }

    public function test_core_tabs_come_first_in_order(): void
    {
        $this->modules(['venue-bookings' => 'Reservas de salas']);

        $tabs = $this->tabs();

        $this->assertSame(['content', 'users', 'site', 'module-venuebookings'], array_map(fn (PermissionTab $t): string => $t->key, $tabs));
        $order = fn (string $tab, array $expected): array => array_values(array_intersect($expected, $this->sectionKeys($this->tab($tabs, $tab))));
        $content = ['events', 'articles', 'galleries', 'hero_slides', 'tags', 'menu_items', 'pages'];
        $site = ['settings', 'updates', 'mail', 'modules', 'dashboard'];
        $this->assertSame($content, $order('content', $content));
        $this->assertSame(['users', 'roles'], $order('users', ['users', 'roles']));
        $this->assertSame($site, $order('site', $site));
    }

    public function test_one_tab_per_enabled_module(): void
    {
        $this->modules(['venue-bookings' => 'Reservas de salas', 'game-tables' => 'Mesas de juego']);

        $tabs = $this->tabs();

        $this->assertSame(['module-gametables', 'module-venuebookings'], array_map(fn (PermissionTab $t): string => $t->key, array_slice($tabs, 3)));
        $this->assertSame('Mesas de juego', $this->tab($tabs, 'module-gametables')->label);
        $this->assertSame(['Reservas', 'rooms'], array_map(fn (PermissionSection $s): string => $s->label, $this->tab($tabs, 'module-venuebookings')->sections));
    }

    public function test_disabled_module_permissions_are_left_out(): void
    {
        $this->modules(['venue-bookings' => 'Reservas de salas', 'game-tables' => 'Mesas de juego'], ['game-tables']);

        $keys = $this->keys($this->tabs());

        $this->assertNotContains('gametables:tables.view_any', $keys);
        $this->assertContains('venuebookings:rooms.view_any', $keys);
    }

    public function test_admin_access_is_not_listed(): void
    {
        $this->modules([]);

        $this->assertNotContains('admin.access', $this->keys($this->tabs()));
    }

    public function test_every_registered_permission_but_admin_access_appears_exactly_once(): void
    {
        $this->modules(['venue-bookings' => 'Reservas', 'game-tables' => 'Mesas']);

        $expected = array_values(array_filter(
            array_map(fn (PermissionDefinitionDTO $d): string => $d->key, app(PermissionRegistryInterface::class)->all()),
            fn (string $key): bool => $key !== 'admin.access',
        ));
        $actual = $this->keys($this->tabs());

        $this->assertEqualsCanonicalizing($expected, $actual);
        $this->assertSame($actual, array_values(array_unique($actual)));
    }

    public function test_description_and_suggestion_come_from_the_registry(): void
    {
        $this->modules(['venue-bookings' => 'Reservas']);

        $option = $this->tab($this->tabs(), 'module-venuebookings')->sections[0]->permissions[0];

        $this->assertSame('venuebookings:bookings.manage', $option->key);
        $this->assertSame('Gestionar reservas', $option->label);
        $this->assertSame('Crear y editar reservas', $option->description);
        $this->assertSame(['editor'], $option->suggestedFor);
        $this->assertNotSame('', $option->id);
    }

    public function test_unknown_core_resource_falls_back_to_site(): void
    {
        $this->modules([]);
        $key = 'widgets.view_any';
        app(PermissionRegistryInterface::class)->register(new PermissionDefinitionDTO($key, 'Ver widgets', 'widgets', 'view_any'));
        PermissionModel::query()->create(['key' => $key, 'label' => 'Ver widgets', 'resource' => 'widgets', 'action' => 'view_any']);

        $site = $this->tab($this->tabs(), 'site');

        $this->assertSame('widgets', array_last($this->sectionKeys($site)));
    }

    public function test_a_permission_missing_from_the_table_is_skipped(): void
    {
        $this->modules(['venue-bookings' => 'Reservas']);
        $this->register('venuebookings:rooms.create', 'Crear salas', 'rooms', 'venuebookings', inDatabase: false);

        $this->assertNotContains('venuebookings:rooms.create', $this->keys($this->tabs()));
    }

    public function test_hyphen_and_underscore_prefixes_map_to_their_modules(): void
    {
        $this->register('cookieconsent:consents.view_any', 'Ver', 'consents', 'cookieconsent');
        $this->register('event_registrations:registrations.view_any', 'Ver', 'registrations', 'event_registrations');
        $this->modules(['cookie-consent' => 'Cookies', 'event-registrations' => 'Inscripciones']);

        $keys = array_map(fn (PermissionTab $t): string => $t->key, $this->tabs());

        $this->assertContains('module-cookieconsent', $keys);
        $this->assertContains('module-event_registrations', $keys);
    }

    public function test_section_keys_do_not_depend_on_the_translated_group_label(): void
    {
        $this->register('cookieconsent:consents.view_any', 'Ver', 'consents', 'cookieconsent', 'Gestión de cookies');
        $this->modules(['cookie-consent' => 'Cookies']);
        $before = $this->sectionKeys($this->tab($this->tabs(), 'module-cookieconsent'));

        app(PermissionRegistryInterface::class)->register(new PermissionDefinitionDTO('cookieconsent:consents.view_any', 'Ver', 'consents', 'view_any', 'cookieconsent', [], null, 'Cookie management'));
        $after = $this->sectionKeys($this->tab($this->tabs(), 'module-cookieconsent'));

        $this->assertSame(['consents'], $before);
        $this->assertSame($before, $after);
    }

    public function test_sections_whose_resources_slug_the_same_get_distinct_keys(): void
    {
        $this->register('cookieconsent:Año.view_any', 'A', 'Año', 'cookieconsent', 'Uno');
        $this->register('cookieconsent:Ano.view_any', 'B', 'Ano', 'cookieconsent', 'Dos');
        $this->modules(['cookie-consent' => 'Cookies']);

        $this->assertSame(['ano', 'ano-2'], $this->sectionKeys($this->tab($this->tabs(), 'module-cookieconsent')));
    }

    public function test_an_empty_slug_falls_back_to_a_numbered_section(): void
    {
        $this->register('cookieconsent:!!!.view_any', 'A', '!!!', 'cookieconsent');
        $this->modules(['cookie-consent' => 'Cookies']);

        $this->assertSame(['section-1'], $this->sectionKeys($this->tab($this->tabs(), 'module-cookieconsent')));
    }

    public function test_module_tabs_sort_ignoring_accents(): void
    {
        $this->modules(['venue-bookings' => 'Zonas', 'game-tables' => 'Álbumes']);
        $this->register('x:a.view_any', 'A', 'a', 'x');

        $keys = array_map(fn (PermissionTab $t): string => $t->key, array_slice($this->tabs(), 3));

        $this->assertSame(['module-gametables', 'module-venuebookings'], $keys);
    }
}
