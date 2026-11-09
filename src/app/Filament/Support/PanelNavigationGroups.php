<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Navigation\NavigationGroup;

/**
 * Builds the sidebar groups registered in the panel: the core's, in PanelNavigationGroup order.
 * Modules place their entries in these groups; they declare none of their own.
 */
final class PanelNavigationGroups
{
    /**
     * Keyed by label: Filament places each group by searching its label among the registered
     * keys followed by the labels, and gives a group nobody registered the position
     * count(registered). With list keys every registered group would sit `count` places later
     * and an unregistered group would tie with the first one, ahead of the core groups.
     *
     * @return array<string, NavigationGroup>
     */
    public static function build(): array
    {
        $groups = [];

        foreach (PanelNavigationGroup::cases() as $case) {
            $label = $case->getLabel();
            $groups[$label] = NavigationGroup::make($label)->collapsed($case->isCollapsed());
        }

        return $groups;
    }
}
