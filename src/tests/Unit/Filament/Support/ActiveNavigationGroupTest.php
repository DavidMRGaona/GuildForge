<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Support;

use App\Filament\Support\ActiveNavigationGroup;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Tests\TestCase;

/**
 * Which sidebar group holds the page being shown, so a collapsed group opens to show it.
 */
final class ActiveNavigationGroupTest extends TestCase
{
    public function test_it_is_the_group_of_the_active_entry(): void
    {
        $navigation = [
            NavigationGroup::make('Contenido')->items([$this->item('Eventos', active: false)]),
            NavigationGroup::make('Sistema')->items([$this->item('Configuración', active: true)]),
        ];

        $this->assertSame('Sistema', ActiveNavigationGroup::label($navigation));
    }

    public function test_an_active_child_entry_counts_for_its_group(): void
    {
        $parent = $this->item('Mesas', active: false)->childItems([$this->item('Campañas', active: true)]);

        $this->assertSame('Actividades', ActiveNavigationGroup::label([
            NavigationGroup::make('Actividades')->items([$parent]),
        ]));
    }

    public function test_there_is_none_when_the_active_entry_has_no_group(): void
    {
        $this->assertNull(ActiveNavigationGroup::label([
            NavigationGroup::make()->items([$this->item('Escritorio', active: true)]),
            NavigationGroup::make('Sistema')->items([$this->item('Módulos', active: false)]),
        ]));
    }

    private function item(string $label, bool $active): NavigationItem
    {
        return NavigationItem::make($label)->url('/'.$label)->isActiveWhen(static fn (): bool => $active);
    }
}
