<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Support;

use App\Filament\Support\PanelNavigationGroups;
use Filament\Navigation\NavigationGroup;
use Tests\TestCase;

/**
 * The sidebar groups: only the core's, in PanelNavigationGroup order (core#409, #429).
 */
final class PanelNavigationGroupsTest extends TestCase
{
    public function test_core_groups_come_in_enum_order(): void
    {
        $this->assertSame(
            ['Contenido', 'Actividades', 'Comunicación', 'Personas', 'Sitio web', 'Catálogos', 'Sistema'],
            $this->labels(PanelNavigationGroups::build()),
        );
    }

    public function test_catalogues_and_system_start_collapsed(): void
    {
        $collapsed = array_values(array_map(
            static fn (NavigationGroup $group): array => [$group->getLabel(), $group->isCollapsed()],
            PanelNavigationGroups::build(),
        ));

        $this->assertSame([
            ['Contenido', false],
            ['Actividades', false],
            ['Comunicación', false],
            ['Personas', false],
            ['Sitio web', false],
            ['Catálogos', true],
            ['Sistema', true],
        ], $collapsed);
    }

    public function test_groups_are_keyed_by_their_label(): void
    {
        foreach (PanelNavigationGroups::build() as $key => $group) {
            $this->assertSame($group->getLabel(), $key);
        }
    }

    /**
     * @param  array<string, NavigationGroup>  $groups
     * @return list<string|null>
     */
    private function labels(array $groups): array
    {
        return array_values(array_map(static fn (NavigationGroup $group): ?string => $group->getLabel(), $groups));
    }
}
