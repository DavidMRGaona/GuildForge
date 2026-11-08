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
     * @param  array<string, array{icon?: string, sort?: int}>  $moduleGroups  label => options
     * @return list<NavigationGroup>
     */
    public static function build(array $moduleGroups): array
    {
        $groups = [];
        $coreLabels = [];

        foreach (PanelNavigationGroup::cases() as $case) {
            $label = $case->getLabel();
            $coreLabels[$label] = true;
            $groups[] = NavigationGroup::make($label)->collapsed($case->isCollapsed());
        }

        // A module group named like a core group is that core group: its items join it
        $moduleGroups = array_diff_key($moduleGroups, $coreLabels);

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

            $groups[] = $group;
        }

        return $groups;
    }
}
