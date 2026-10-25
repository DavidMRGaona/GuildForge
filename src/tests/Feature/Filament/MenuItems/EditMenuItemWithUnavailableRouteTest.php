<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\MenuItems;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Filament\Resources\MenuItemResource\Pages\EditMenuItem;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A stored route can stop being selectable (a module was uninstalled, or an old sync
 * copied an admin route). The page select must still accept it, or the admin cannot
 * even deactivate the link: Filament validates the whole form against the options.
 */
final class EditMenuItemWithUnavailableRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string ADMIN_ROUTE = 'filament.admin.resources.announcements.index';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_a_link_whose_route_is_no_longer_selectable_can_still_be_deactivated(): void
    {
        $item = $this->itemWithRoute(self::ADMIN_ROUTE);

        Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $item->refresh();
        $this->assertFalse($item->is_active);
        $this->assertSame(self::ADMIN_ROUTE, $item->route);
    }

    public function test_the_unavailable_route_is_shown_as_such_instead_of_its_raw_name(): void
    {
        $item = $this->itemWithRoute(self::ADMIN_ROUTE);

        Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])
            ->assertFormFieldExists('route', fn ($field): bool => $field->getOptions()[self::ADMIN_ROUTE] === __('filament.menu_items.fields.page_unavailable', ['route' => self::ADMIN_ROUTE]))
            ->assertSee(__('filament.menu_items.fields.page_unavailable', ['route' => self::ADMIN_ROUTE]));
    }

    public function test_a_selectable_route_is_not_offered_twice(): void
    {
        $item = $this->itemWithRoute('events.index');

        Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])
            ->assertFormFieldExists('route', fn ($field): bool => array_count_values(array_keys($field->getOptions()))['events.index'] === 1
                && $field->getOptions()['events.index'] === __('navigation.routes.events'));
    }

    private function itemWithRoute(string $route): MenuItemModel
    {
        return MenuItemModel::factory()->create([
            'location' => MenuLocation::Header,
            'label' => 'Anuncios',
            'route' => $route,
            'module' => 'announcements',
            'is_active' => true,
        ]);
    }
}
