<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

/**
 * The sidebar group that holds the page being shown. Filament keeps a collapsed group collapsed
 * even when its page is open, so the active entry would be hidden; the panel opens that group
 * (resources/views/filament/components/expand-active-navigation-group.blade.php).
 */
final class ActiveNavigationGroup
{
    /**
     * @param  array<NavigationGroup>  $navigation
     */
    public static function label(array $navigation): ?string
    {
        foreach ($navigation as $group) {
            $label = $group->getLabel();

            if (blank($label)) {
                continue;
            }

            $active = collect($group->getItems())->contains(
                static fn (NavigationItem $item): bool => $item->isActive() || $item->isChildItemsActive(),
            );

            if ($active) {
                return (string) $label;
            }
        }

        return null;
    }
}
