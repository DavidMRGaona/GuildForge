<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\MenuItems;

use App\Application\Navigation\Services\FooterMenusInterface;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Filament\Resources\MenuItemResource\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class FooterMenuLocationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_the_extra_footer_menus_get_a_tab_once_named_or_used(): void
    {
        $this->assertSame(['header', 'footer'], array_keys($this->tabs()));

        app(FooterMenusInterface::class)->saveNames(['footer_3' => 'Comunidad']);
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer2, 'label' => 'Discord']);

        $tabs = $this->tabs();
        $this->assertSame(['header', 'footer', 'footer_2', 'footer_3'], array_keys($tabs));
        $this->assertSame('Pie de página 2', $tabs['footer_2']->getLabel());
        $this->assertSame('Comunidad', $tabs['footer_3']->getLabel());
    }

    public function test_each_tab_lists_its_own_menu(): void
    {
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer, 'label' => 'Contacto']);
        $other = MenuItemModel::factory()->create(['location' => MenuLocation::Footer2, 'label' => 'Discord']);

        Livewire::test(ListMenuItems::class, ['activeTab' => 'footer_2'])
            ->assertCanSeeTableRecords([$other])
            ->assertDontSee('Contacto');
    }

    public function test_a_link_can_go_in_any_footer_menu(): void
    {
        app(FooterMenusInterface::class)->saveNames(['footer_2' => 'Comunidad']);

        Livewire::test(CreateMenuItem::class)
            ->assertFormFieldExists('location', fn ($field): bool => $field->getOptions() === [
                'header' => 'Cabecera',
                'footer' => 'Pie de página',
                'footer_2' => 'Comunidad',
                'footer_3' => 'Pie de página 3',
                'footer_4' => 'Pie de página 4',
            ])
            ->fillForm([
                'location' => 'footer_2',
                'label' => 'Discord',
                'url' => 'https://discord.gg/ejemplo',
                'target' => '_self',
                'visibility' => 'public',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(MenuLocation::Footer2, MenuItemModel::query()->where('label', 'Discord')->firstOrFail()->location);
    }

    /**
     * @return array<string, Tab>
     */
    private function tabs(): array
    {
        $page = Livewire::test(ListMenuItems::class)->instance();
        $this->assertInstanceOf(ListMenuItems::class, $page);

        return $page->getTabs();
    }
}
