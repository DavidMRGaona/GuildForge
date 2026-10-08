<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Services;

use App\Application\Navigation\Services\MenuItemHrefResolverInterface;
use App\Domain\Navigation\Entities\MenuItem;
use Illuminate\Routing\Exceptions\UrlGenerationException;
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
            if (! Route::has($route)) {
                return null;
            }

            try {
                return route($route, $menuItem->routeParams());
            } catch (UrlGenerationException) {
                // The stored parameters no longer satisfy the route's required ones
                return null;
            }
        }

        return '#';
    }
}
