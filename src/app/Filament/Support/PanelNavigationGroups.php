<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Navigation\NavigationGroup;

/**
 * Builds the sidebar groups registered in the panel: the core's, in PanelNavigationGroup order,
 * then the groups modules not migrated yet still declare in registerNavigationGroups().
 */
final class PanelNavigationGroups
{
    private const int DEFAULT_MODULE_SORT = 100;

    /**
     * Keyed by label: Filament places each group by searching its label among the registered
     * keys followed by the labels, and gives a group nobody registered the position
     * count(registered). With list keys every registered group would sit `count` places later
     * and an unregistered group would tie with the first one, ahead of the core groups.
     *
     * @param  array<string, array{icon?: string, sort?: int}>  $moduleGroups  label => options
     * @return array<string, NavigationGroup>
     */
    public static function build(array $moduleGroups): array
    {
        $groups = [];

        foreach (PanelNavigationGroup::cases() as $case) {
            $label = $case->getLabel();
            $groups[$label] = NavigationGroup::make($label)->collapsed($case->isCollapsed());
        }

        // A module group named like a core group is that core group: its items join it
        $moduleGroups = array_diff_key($moduleGroups, $groups);

        uksort($moduleGroups, static function (string $labelA, string $labelB) use ($moduleGroups): int {
            $sortA = $moduleGroups[$labelA]['sort'] ?? self::DEFAULT_MODULE_SORT;
            $sortB = $moduleGroups[$labelB]['sort'] ?? self::DEFAULT_MODULE_SORT;

            return $sortA <=> $sortB ?: $labelA <=> $labelB;
        });

        foreach ($moduleGroups as $label => $options) {
            $group = NavigationGroup::make($label);

            if (isset($options['icon'])) {
                $group->icon($options['icon']);
            }

            $groups[$label] = $group;
        }

        return $groups;
    }
}
