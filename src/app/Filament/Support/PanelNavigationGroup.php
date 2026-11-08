<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Support\Contracts\HasLabel;

/**
 * The panel sidebar groups, in the order they are shown. Core and module resources and pages
 * name their group with `PanelNavigationGroup::X->getLabel()`, a string: Filament groups items
 * by the serialized group, so an enum case and a module that still returns the same label as
 * text would become two groups sharing one heading, and one of them would lose its entries.
 *
 * Entry slots (sort values) per group are listed in docs/modules/README.md.
 */
enum PanelNavigationGroup: string implements HasLabel
{
    case Content = 'content';
    case Activities = 'activities';
    case Communication = 'communication';
    case People = 'people';
    case Site = 'site';
    case Catalogues = 'catalogues';
    case System = 'system';

    public function getLabel(): string
    {
        return __("filament.navigation_groups.{$this->value}");
    }

    /**
     * Groups opened rarely start collapsed; Filament remembers per browser when someone opens them.
     */
    public function isCollapsed(): bool
    {
        return match ($this) {
            self::Catalogues, self::System => true,
            default => false,
        };
    }
}
