<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Services;

use App\Application\Navigation\Services\MenuItemHrefResolverInterface;
use App\Domain\Navigation\Entities\MenuItem;
use Illuminate\Support\Facades\Route;

final readonly class MenuItemHrefResolver implements MenuItemHrefResolverInterface
{
    public function resolve(MenuItem $menuItem): ?string
    {
        $url = $menuItem->url();
        if ($url !== null && $url !== '') {
            return $url;
        }

        $route = $menuItem->route();
        if ($route !== null && $route !== '') {
            // Routes of a disabled or rejected module are not registered in this process
            return Route::has($route) ? route($route, $menuItem->routeParams()) : null;
        }

        return '#';
    }
}
