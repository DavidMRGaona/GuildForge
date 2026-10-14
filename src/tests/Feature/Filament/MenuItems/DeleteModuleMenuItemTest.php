<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\MenuItems;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Modules no longer own menu links (nothing creates them on the module's behalf), so a
 * link tagged with a module is deletable like any other. Only the core's seeded links
 * stay protected: the production seeder would bring them back on the next deploy.
 */
final class DeleteModuleMenuItemTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_a_link_contributed_by_a_module_can_be_deleted(): void
    {
        $item = $this->item('Anuncios', 'filament.admin.resources.announcements.index', 'announcements');

        Livewire::test(ListMenuItems::class)
            ->assertTableActionVisible(DeleteAction::class, $item)
            ->callTableAction(DeleteAction::class, $item)
            ->assertHasNoTableActionErrors();

        $this->assertNull($item->fresh());
    }

    public function test_a_core_link_cannot_be_deleted(): void
    {
        $item = $this->item('Inicio', 'home', 'core');

        Livewire::test(ListMenuItems::class)
            ->assertTableActionHidden(DeleteAction::class, $item);

        $this->assertNotNull($item->fresh());
    }

    public function test_bulk_delete_removes_module_links_and_keeps_core_links(): void
    {
        $module = $this->item('Socios', 'filament.admin.resources.members.index', 'memberships');
        $core = $this->item('Eventos', 'events.index', 'core');

        Livewire::test(ListMenuItems::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$module, $core]);

        $this->assertNull($module->fresh());
        $this->assertNotNull($core->fresh());
    }

    private function item(string $label, string $route, string $module): MenuItemModel
    {
        return MenuItemModel::factory()->create([
            'location' => MenuLocation::Header,
            'label' => $label,
            'route' => $route,
            'module' => $module,
        ]);
    }
}
