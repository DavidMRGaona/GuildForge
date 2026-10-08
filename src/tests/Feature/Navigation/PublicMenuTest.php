<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PublicMenuTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_links_to_routes_of_modules_that_are_not_loaded_are_left_out(): void
    {
        $this->menuItem(MenuLocation::Header, 'Eventos', url: '/eventos', sort: 1);
        // A tenant's real menu: four links to module pages whose module is not loaded
        $this->menuItem(MenuLocation::Header, 'Reservas', route: 'bookings.index', module: 'venue-bookings', sort: 2);
        $this->menuItem(MenuLocation::Header, 'Mesas', route: 'gametables.index', module: 'game-tables', sort: 3);
        $this->menuItem(MenuLocation::Header, 'Campañas', route: 'campaigns.index', module: 'game-tables', sort: 4);
        $this->menuItem(MenuLocation::Header, 'Anuncios', route: 'filament.admin.resources.announcements.index', module: 'announcements', sort: 5);
        $this->menuItem(MenuLocation::Footer, 'Contacto', url: '/contacto', sort: 1);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('navigation.header', 1)
                ->where('navigation.header.0.label', 'Eventos')
                ->where('navigation.header.0.href', '/eventos')
                ->has('navigation.footer', 1)
                ->where('navigation.footer.0.label', 'Contacto'));
    }

    public function test_a_child_link_to_an_unloaded_module_route_leaves_its_siblings(): void
    {
        $parent = $this->menuItem(MenuLocation::Header, 'Comunidad', url: '/comunidad', sort: 1);
        $this->menuItem(MenuLocation::Header, 'Galería', url: '/galeria', sort: 1, parentId: $parent->id);
        $this->menuItem(MenuLocation::Header, 'Mesas', route: 'gametables.index', module: 'game-tables', sort: 2, parentId: $parent->id);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('navigation.header', 1)
                ->has('navigation.header.0.children', 1)
                ->where('navigation.header.0.children.0.label', 'Galería'));
    }

    public function test_a_link_whose_route_lacks_its_parameters_leaves_the_rest_of_the_menu(): void
    {
        $this->menuItem(MenuLocation::Header, 'Eventos', url: '/eventos', sort: 1);
        // articles.show needs {slug} and the item stores no route parameters
        $this->menuItem(MenuLocation::Header, 'Artículo', route: 'articles.show', sort: 2);
        $this->menuItem(MenuLocation::Footer, 'Contacto', url: '/contacto', sort: 1);
        $this->menuItem(MenuLocation::Footer, 'Artículo', route: 'articles.show', sort: 2);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('navigation.header', 1)
                ->where('navigation.header.0.label', 'Eventos')
                ->has('navigation.footer', 1)
                ->where('navigation.footer.0.label', 'Contacto'));
    }

    public function test_links_to_registered_routes_keep_their_url(): void
    {
        $this->menuItem(MenuLocation::Header, 'Artículos', route: 'articles.index', sort: 1);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('navigation.header', 1)
                ->where('navigation.header.0.href', route('articles.index')));
    }

    private function menuItem(
        MenuLocation $location,
        string $label,
        ?string $url = null,
        ?string $route = null,
        ?string $module = null,
        int $sort = 0,
        ?string $parentId = null,
    ): MenuItemModel {
        return MenuItemModel::factory()->create([
            'location' => $location,
            'label' => $label,
            'url' => $url,
            'route' => $route,
            'module' => $module,
            'sort_order' => $sort,
            'parent_id' => $parentId,
        ]);
    }
}
