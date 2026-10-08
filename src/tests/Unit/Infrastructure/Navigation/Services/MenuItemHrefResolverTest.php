<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Navigation\Services;

use App\Domain\Navigation\Entities\MenuItem;
use App\Domain\Navigation\Enums\LinkTarget;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Enums\MenuVisibility;
use App\Domain\Navigation\ValueObjects\MenuItemId;
use App\Infrastructure\Navigation\Services\MenuItemHrefResolver;
use Tests\TestCase;

final class MenuItemHrefResolverTest extends TestCase
{
    public function test_the_url_wins_over_the_route(): void
    {
        $this->assertSame('/eventos', (new MenuItemHrefResolver())->resolve($this->item(url: '/eventos', route: 'articles.index')));
    }

    public function test_a_registered_route_is_turned_into_its_url(): void
    {
        $this->assertSame(route('articles.index'), (new MenuItemHrefResolver())->resolve($this->item(route: 'articles.index')));
    }

    public function test_a_route_that_is_not_registered_resolves_to_nothing(): void
    {
        // Routes of a module that is disabled or rejected by the compatibility gate
        $this->assertNull((new MenuItemHrefResolver())->resolve($this->item(route: 'gametables.index')));
    }

    public function test_an_item_without_url_or_route_points_nowhere(): void
    {
        $this->assertSame('#', (new MenuItemHrefResolver())->resolve($this->item()));
    }

    private function item(?string $url = null, ?string $route = null): MenuItem
    {
        return new MenuItem(
            id: MenuItemId::generate(),
            location: MenuLocation::Header,
            parentId: null,
            label: 'Enlace',
            url: $url,
            route: $route,
            routeParams: [],
            icon: null,
            target: LinkTarget::Self,
            visibility: MenuVisibility::Public,
            permissions: [],
            sortOrder: 0,
            isActive: true,
            module: null,
        );
    }
}
