<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Navigation\Services;

use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Infrastructure\Modules\Services\ModuleRouteRegistry;
use App\Infrastructure\Navigation\Services\RouteRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RouteRegistryTest extends TestCase
{
    #[Test]
    public function it_returns_core_routes(): void
    {
        $moduleRouteRegistry = new ModuleRouteRegistry();
        $registry = new RouteRegistry($moduleRouteRegistry);

        $routes = $registry->getAvailableRoutes();

        $this->assertArrayHasKey('home', $routes);
        $this->assertArrayHasKey('about', $routes);
        $this->assertArrayHasKey('events.index', $routes);
        $this->assertArrayHasKey('login', $routes);
    }

    #[Test]
    public function it_merges_module_routes_with_core_routes(): void
    {
        $moduleRouteRegistry = new ModuleRouteRegistry();
        $moduleRouteRegistry->register(
            new ModuleRouteDTO(
                routeName: 'gametables.index',
                label: 'Mesas de rol',
                module: 'game-tables',
            )
        );

        $registry = new RouteRegistry($moduleRouteRegistry);
        $routes = $registry->getAvailableRoutes();

        $this->assertArrayHasKey('home', $routes);
        $this->assertArrayHasKey('events.index', $routes);
        $this->assertArrayHasKey('gametables.index', $routes);
        $this->assertSame('Mesas de rol', $routes['gametables.index']);
    }

    #[Test]
    public function it_gives_priority_to_core_routes_on_key_collision(): void
    {
        $moduleRouteRegistry = new ModuleRouteRegistry();
        $moduleRouteRegistry->register(
            new ModuleRouteDTO(
                routeName: 'home',
                label: 'Module home',
                module: 'custom-module',
            )
        );

        $registry = new RouteRegistry($moduleRouteRegistry);
        $routes = $registry->getAvailableRoutes();

        $this->assertArrayHasKey('home', $routes);
        $this->assertNotSame('Module home', $routes['home']);
    }
}
