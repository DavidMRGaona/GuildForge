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

    public function test_core_tabs_come_first_in_order(): void
    {
        $this->modules(['venue-bookings' => 'Reservas de salas']);

        $tabs = $this->tabs();

        $this->assertSame(['content', 'users', 'site', 'module-venuebookings'], array_map(fn (PermissionTab $t): string => $t->key, $tabs));
        $this->assertSame(
            ['events', 'articles', 'galleries', 'hero_slides', 'tags', 'menu_items', 'pages'],
            array_map(fn (PermissionSection $s): string => $s->key, array_slice($tabs[0]->sections, 0, 7)),
        );
        $this->assertSame(['users', 'roles'], array_map(fn (PermissionSection $s): string => $s->key, $tabs[1]->sections));
        $this->assertSame(
            ['settings', 'updates', 'mail', 'modules', 'dashboard'],
            array_map(fn (PermissionSection $s): string => $s->key, array_slice($tabs[2]->sections, 0, 5)),
        );
    }

    public function test_one_tab_per_enabled_module(): void
    {
        $this->modules(['venue-bookings' => 'Reservas de salas', 'game-tables' => 'Mesas de juego']);

        $tabs = array_slice($this->tabs(), 3);

        $this->assertSame(['module-gametables', 'module-venuebookings'], array_map(fn (PermissionTab $t): string => $t->key, $tabs));
        $this->assertSame('Mesas de juego', $tabs[0]->label);
        $this->assertSame(['Reservas', 'rooms'], array_map(fn (PermissionSection $s): string => $s->label, $tabs[1]->sections));
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

        $option = $this->tabs()[3]->sections[0]->permissions[0];

        $this->assertSame('venuebookings:bookings.manage', $option->key);
        $this->assertSame('Gestionar reservas', $option->label);
        $this->assertSame('Crear y editar reservas', $option->description);
        $this->assertSame(['editor'], $option->suggestedFor);
        $this->assertNotSame('', $option->id);
    }
}
