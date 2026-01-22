<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\Services\ModuleNavigationRegistryInterface;

final class ModuleNavigationRegistry implements ModuleNavigationRegistryInterface
{
    /** @var array<NavigationItemDTO> */
    private array $items = [];

    public function register(NavigationItemDTO $item): void
    {
        $this->items[] = $item;
    }

    public function registerMany(array $items): void
    {
        foreach ($items as $item) {
            $this->register($item);
        }
    }

    public function all(): array
    {
        return $this->items;
    }

    public function forModule(string $moduleName): array
    {
        return array_values(
            array_filter(
                $this->items,
                fn (NavigationItemDTO $item): bool => $item->module === $moduleName
            )
        );
    }

    public function grouped(): array
    {
        $groups = [];

        foreach ($this->items as $item) {
            $group = $item->group;
            if (! isset($groups[$group])) {
                $groups[$group] = [];
            }
            $groups[$group][] = $item;
        }

        // Sort items within each group by sort order
        foreach ($groups as $group => $items) {
            usort($items, fn (NavigationItemDTO $a, NavigationItemDTO $b): int => $a->sort <=> $b->sort);
            $groups[$group] = $items;
        }

        ksort($groups);

        return $groups;
    }

    public function sorted(): array
    {
        $sorted = $this->items;
        usort($sorted, fn (NavigationItemDTO $a, NavigationItemDTO $b): int => $a->sort <=> $b->sort);

        return $sorted;
    }

    public function forUser(array $userPermissions): array
    {
        return array_values(
            array_filter(
                $this->items,
                function (NavigationItemDTO $item) use ($userPermissions): bool {
                    // If no permissions required, show to everyone
                    if (! $item->requiresPermission()) {
                        return true;
                    }

                    // Check if user has any of the required permissions
                    return count(array_intersect($item->permissions, $userPermissions)) > 0;
                }
            )
        );
    }

    public function unregisterModule(string $moduleName): void
    {
        $this->items = array_values(
            array_filter(
                $this->items,
                fn (NavigationItemDTO $item): bool => $item->module !== $moduleName
            )
        );
    }

    public function clear(): void
    {
        $this->items = [];
    }
}
