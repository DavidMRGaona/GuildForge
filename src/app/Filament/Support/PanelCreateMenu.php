<?php

declare(strict_types=1);

namespace App\Filament\Support;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Resources\Resource as FilamentResource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * The «Crear» menu of the top bar: every resource of the panel, core or module, whose create
 * page the signed-in user may open, grouped and ordered as in the sidebar.
 */
final class PanelCreateMenu
{
    /**
     * @return list<array{label: string|null, items: list<array{label: string, icon: string|BackedEnum|Htmlable|null, url: string}>}>
     */
    public static function groups(): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        if ($panel === null) {
            return [];
        }

        $byGroup = [];

        foreach ($panel->getResources() as $resource) {
            if (! is_subclass_of($resource, FilamentResource::class)) {
                continue;
            }

            $item = self::item($resource);
            if ($item === null) {
                continue;
            }

            $byGroup[self::groupLabel($resource::getNavigationGroup())][] = $item;
        }

        $groups = [];
        foreach (self::orderedGroupLabels(array_keys($byGroup), $panel->getNavigationGroups()) as $label) {
            $items = $byGroup[$label];
            usort($items, static fn (array $a, array $b): int => [$a['sort'], $a['label']] <=> [$b['sort'], $b['label']]);

            $groups[] = [
                'label' => $label === '' ? null : $label,
                'items' => array_map(
                    static fn (array $item): array => ['label' => $item['label'], 'icon' => $item['icon'], 'url' => $item['url']],
                    $items,
                ),
            ];
        }

        return $groups;
    }

    /**
     * @param  class-string<FilamentResource>  $resource
     * @return array{sort: int, label: string, icon: string|BackedEnum|Htmlable|null, url: string}|null
     */
    private static function item(string $resource): ?array
    {
        // A broken module resource leaves the menu without it, never the whole panel without a top bar
        try {
            if (! $resource::hasPage('create') || ! $resource::canCreate()) {
                return null;
            }

            return [
                'sort' => $resource::getNavigationSort() ?? PHP_INT_MAX,
                'label' => Str::ucfirst($resource::getModelLabel()),
                'icon' => $resource::getNavigationIcon(),
                'url' => $resource::getUrl('create'),
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function groupLabel(string|UnitEnum|null $group): string
    {
        return match (true) {
            $group instanceof BackedEnum => (string) $group->value,
            $group instanceof UnitEnum => $group->name,
            default => (string) $group,
        };
    }

    /**
     * The sidebar's group order first, then any other group, and ungrouped resources last.
     *
     * @param  list<string>  $labels
     * @param  array<int|string, NavigationGroup|string>  $navigationGroups
     * @return list<string>
     */
    private static function orderedGroupLabels(array $labels, array $navigationGroups): array
    {
        $order = [];
        foreach ($navigationGroups as $key => $group) {
            $order[] = $group instanceof NavigationGroup ? (string) $group->getLabel() : (is_string($key) ? $key : $group);
        }

        usort($labels, static function (string $a, string $b) use ($order): int {
            $position = static fn (string $label): int => match (true) {
                $label === '' => PHP_INT_MAX,
                in_array($label, $order, true) => (int) array_search($label, $order, true),
                default => PHP_INT_MAX - 1,
            };

            return [$position($a), $a] <=> [$position($b), $b];
        });

        return $labels;
    }
}
