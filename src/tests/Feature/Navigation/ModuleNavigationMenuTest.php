<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\Services\ModuleNavigationRegistryInterface;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Infrastructure\Navigation\Listeners\ActivateMenuItemsOnModuleEnabled;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * A module's registerNavigation() describes the admin panel sidebar. It must never be
 * copied into the public menu: the links pointed at /admin pages and the menu editor
 * could not even deactivate them, because admin routes are not selectable pages.
 */
final class ModuleNavigationMenuTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_enabling_a_module_does_not_copy_its_panel_navigation_into_the_public_menu(): void
    {
        app(ModuleNavigationRegistryInterface::class)->register(new NavigationItemDTO(
            label: 'Anuncios',
            route: 'filament.admin.resources.announcements.index',
            icon: 'heroicon-o-megaphone',
            permissions: ['announcements:announcements.view_any'],
            module: 'announcements',
        ));

        app(ActivateMenuItemsOnModuleEnabled::class)
            ->handle(new ModuleEnabled(moduleId: 'announcements', moduleName: 'announcements'));

        $this->assertSame(0, MenuItemModel::query()->where('module', 'announcements')->count());
    }

    public function test_enabling_a_module_reactivates_the_menu_links_an_admin_created_for_it(): void
    {
        $item = MenuItemModel::factory()->create([
            'location' => MenuLocation::Header,
            'label' => 'Reservas',
            'route' => 'bookings.index',
            'module' => 'venue-bookings',
            'is_active' => false,
        ]);

        app(ActivateMenuItemsOnModuleEnabled::class)
            ->handle(new ModuleEnabled(moduleId: 'venue-bookings', moduleName: 'venue-bookings'));

        $this->assertTrue($item->fresh()->is_active);
    }
}
