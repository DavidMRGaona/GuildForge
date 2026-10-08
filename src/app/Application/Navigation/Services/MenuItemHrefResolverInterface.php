<?php

declare(strict_types=1);

namespace App\Application\Navigation\Services;

use App\Domain\Navigation\Entities\MenuItem;

interface MenuItemHrefResolverInterface
{
    /**
     * Resolve the href for a menu item.
     * Returns the URL if set, otherwise generates from route; null when the route is
     * not registered (its module is disabled or rejected), so the item is left out.
     */
    public function resolve(MenuItem $menuItem): ?string;
}
