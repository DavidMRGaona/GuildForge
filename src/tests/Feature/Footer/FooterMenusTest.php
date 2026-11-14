<?php

declare(strict_types=1);

namespace Tests\Feature\Footer;

use App\Application\Navigation\Services\FooterMenusInterface;
use App\Application\Navigation\Services\MenuServiceInterface;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class FooterMenusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_footer_menus_have_default_names(): void
    {
        $this->assertSame([
            'footer' => 'Pie de página',
            'footer_2' => 'Pie de página 2',
            'footer_3' => 'Pie de página 3',
            'footer_4' => 'Pie de página 4',
        ], app(FooterMenusInterface::class)->options());
        $this->assertSame('Cabecera', app(FooterMenusInterface::class)->label(MenuLocation::Header));
    }

    public function test_names_are_saved_and_blank_ones_fall_back(): void
    {
        $menus = app(FooterMenusInterface::class);

        $menus->saveNames([
            'footer' => '  ',
            'footer_2' => '  Comunidad ',
            'header' => 'Ignorado',
            'otro' => 'Ignorado',
            'footer_3' => ['no'],
            'footer_4' => str_repeat('a', 80),
        ]);

        $this->assertSame(['footer_2' => 'Comunidad', 'footer_4' => str_repeat('a', FooterMenusInterface::MAX_NAME_LENGTH)], $menus->names());
        $this->assertSame('Pie de página', $menus->label(MenuLocation::Footer));
        $this->assertSame('Comunidad', $menus->label(MenuLocation::Footer2));
        $this->assertSame('Pie de página 3', $menus->label(MenuLocation::Footer3));
    }

    public function test_each_footer_menu_has_its_own_links(): void
    {
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer, 'label' => 'Contacto', 'url' => '/nosotros', 'route' => null]);
        MenuItemModel::factory()->create(['location' => MenuLocation::Footer2, 'label' => 'Discord', 'url' => 'https://discord.gg/x', 'route' => null]);

        $menus = app(MenuServiceInterface::class);

        $this->assertSame(['Contacto'], array_map(static fn ($item): string => $item->label, $menus->getFooterMenu(null)));
        $this->assertSame(['Discord'], array_map(static fn ($item): string => $item->label, $menus->getMenu(MenuLocation::Footer2, null)));
        $this->assertSame([], $menus->getMenu(MenuLocation::Footer3, null));
    }
}
