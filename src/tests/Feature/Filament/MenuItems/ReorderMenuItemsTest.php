<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\MenuItems;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ReorderMenuItemsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_dragging_rows_stores_the_new_order(): void
    {
        $first = MenuItemModel::factory()->create(['location' => MenuLocation::Header, 'label' => 'Inicio', 'sort_order' => 1]);
        $second = MenuItemModel::factory()->create(['location' => MenuLocation::Header, 'label' => 'Eventos', 'sort_order' => 2]);
        $child = MenuItemModel::factory()->create(['location' => MenuLocation::Header, 'label' => 'Mesas', 'sort_order' => 1, 'parent_id' => $second->id]);

        Livewire::test(ListMenuItems::class, ['activeTab' => 'header'])
            ->call('reorderTable', [(string) $second->id, (string) $child->id, (string) $first->id])
            ->assertHasNoErrors();

        $this->assertSame(1, $second->fresh()?->sort_order);
        $this->assertSame(2, $child->fresh()?->sort_order);
        $this->assertSame(3, $first->fresh()?->sort_order);
    }

    public function test_children_are_listed_right_after_their_parent(): void
    {
        $games = MenuItemModel::factory()->create(['location' => MenuLocation::Header, 'label' => 'Partidas', 'sort_order' => 2]);
        $home = MenuItemModel::factory()->create(['location' => MenuLocation::Header, 'label' => 'Inicio', 'sort_order' => 1]);
        $forum = MenuItemModel::factory()->create(['location' => MenuLocation::Header, 'label' => 'Foro', 'sort_order' => 3]);
        $campaigns = MenuItemModel::factory()->create(['location' => MenuLocation::Header, 'label' => 'Campañas', 'sort_order' => 2, 'parent_id' => $games->id]);
        $tables = MenuItemModel::factory()->create(['location' => MenuLocation::Header, 'label' => 'Mesas', 'sort_order' => 1, 'parent_id' => $games->id]);

        Livewire::test(ListMenuItems::class, ['activeTab' => 'header'])
            ->assertCanSeeTableRecords([$home, $games, $tables, $campaigns, $forum], inOrder: true);
    }
}
