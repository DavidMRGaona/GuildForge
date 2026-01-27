<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Services;

use App\Application\Navigation\Services\MenuItemHrefResolverInterface;
use App\Domain\Navigation\Entities\MenuItem;

final readonly class MenuItemHrefResolver implements MenuItemHrefResolverInterface
{
    public function resolve(MenuItem $menuItem): string
    {
        $url = $menuItem->url();
        if ($url !== null && $url !== '') {
            return $url;
        }

        $route = $menuItem->route();
        if ($route !== null && $route !== '') {
            return route($route, $menuItem->routeParams());
        }

        return '#';
    }
}
