<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Support;

use App\Filament\Support\PanelNavigationGroups;
use Filament\Navigation\NavigationGroup;
use Tests\TestCase;

/**
 * The sidebar groups: the core's, in its order, then whatever modules not yet migrated still
 * declare through registerNavigationGroups().
 */
final class PanelNavigationGroupsTest extends TestCase
{
    public function test_core_groups_come_first_in_enum_order(): void
    {
        $this->assertSame(
            ['Contenido', 'Actividades', 'Comunicación', 'Personas', 'Sitio web', 'Catálogos', 'Sistema'],
            $this->labels(PanelNavigationGroups::build([])),
        );
    }

    public function test_catalogues_and_system_start_collapsed(): void
    {
        $collapsed = array_values(array_map(
            static fn (NavigationGroup $group): array => [$group->getLabel(), $group->isCollapsed()],
            PanelNavigationGroups::build([]),
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

    public function test_legacy_module_groups_follow_by_sort_then_label(): void
    {
        $labels = $this->labels(PanelNavigationGroups::build([
            'Torneos' => ['sort' => 11],
            'Encuestas' => [],
            'Mesas de rol' => ['sort' => 11],
        ]));

        $this->assertSame(['Mesas de rol', 'Torneos', 'Encuestas'], array_slice($labels, 7));
    }

    public function test_a_legacy_group_named_like_a_core_group_is_not_added_twice(): void
    {
        $labels = $this->labels(PanelNavigationGroups::build(['Comunicación' => ['sort' => 20]]));

        $this->assertCount(1, array_keys($labels, 'Comunicación', true));
        $this->assertCount(7, $labels);
    }

    public function test_a_legacy_group_keeps_its_icon(): void
    {
        $groups = PanelNavigationGroups::build(['Mesas de rol' => ['icon' => 'heroicon-o-puzzle-piece']]);

        $this->assertSame('heroicon-o-puzzle-piece', $groups['Mesas de rol']->getIcon());
    }

    public function test_groups_are_keyed_by_their_label(): void
    {
        foreach (PanelNavigationGroups::build(['Mesas de rol' => []]) as $key => $group) {
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
