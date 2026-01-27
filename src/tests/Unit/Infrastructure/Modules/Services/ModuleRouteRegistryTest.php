<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Infrastructure\Modules\Services\ModuleRouteRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModuleRouteRegistryTest extends TestCase
{
    private ModuleRouteRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ModuleRouteRegistry();
    }

    #[Test]
    public function it_can_register_a_route(): void
    {
        $route = new ModuleRouteDTO(
            routeName: 'gametables.index',
            label: 'Mesas de rol',
            module: 'game-tables',
        );

        $this->registry->register($route);

        $this->assertCount(1, $this->registry->all());
    }

    #[Test]
    public function it_can_register_many_routes(): void
    {
        $routes = [
            new ModuleRouteDTO(
                routeName: 'gametables.index',
                label: 'Mesas de rol',
                module: 'game-tables',
            ),
            new ModuleRouteDTO(
                routeName: 'tournaments.index',
                label: 'Torneos',
                module: 'tournaments',
            ),
        ];

        $this->registry->registerMany($routes);

        $this->assertCount(2, $this->registry->all());
    }

    #[Test]
    public function it_returns_routes_for_specific_module(): void
    {
        $this->registry->registerMany([
            new ModuleRouteDTO(
                routeName: 'gametables.index',
                label: 'Mesas de rol',
                module: 'module-a',
            ),
            new ModuleRouteDTO(
                routeName: 'gametables.show',
                label: 'Detalle de mesa',
                module: 'module-a',
            ),
            new ModuleRouteDTO(
                routeName: 'tournaments.index',
                label: 'Torneos',
                module: 'module-b',
            ),
        ]);

        $moduleARoutes = $this->registry->forModule('module-a');

        $this->assertCount(2, $moduleARoutes);
        foreach ($moduleARoutes as $route) {
            $this->assertSame('module-a', $route->module);
        }
    }

    #[Test]
    public function it_can_unregister_module_routes(): void
    {
        $this->registry->registerMany([
            new ModuleRouteDTO(
                routeName: 'gametables.index',
                label: 'Mesas de rol',
                module: 'game-tables',
            ),
            new ModuleRouteDTO(
                routeName: 'tournaments.index',
                label: 'Torneos',
                module: 'tournaments',
            ),
        ]);

        $this->registry->unregisterModule('game-tables');

        $this->assertCount(1, $this->registry->all());
        $this->assertSame('tournaments', $this->registry->all()[0]->module);
    }

    #[Test]
    public function it_can_clear_all_routes(): void
    {
        $this->registry->registerMany([
            new ModuleRouteDTO(
                routeName: 'gametables.index',
                label: 'Mesas de rol',
                module: 'game-tables',
            ),
            new ModuleRouteDTO(
                routeName: 'tournaments.index',
                label: 'Torneos',
                module: 'tournaments',
            ),
        ]);

        $this->registry->clear();

        $this->assertCount(0, $this->registry->all());
    }

    #[Test]
    public function it_converts_to_route_options(): void
    {
        $this->registry->registerMany([
            new ModuleRouteDTO(
                routeName: 'gametables.index',
                label: 'Mesas de rol',
                module: 'game-tables',
            ),
            new ModuleRouteDTO(
                routeName: 'tournaments.index',
                label: 'Torneos',
                module: 'tournaments',
            ),
        ]);

        $options = $this->registry->toRouteOptions();

        $this->assertSame([
            'gametables.index' => 'Mesas de rol',
            'tournaments.index' => 'Torneos',
        ], $options);
    }

    #[Test]
    public function for_module_returns_empty_array_when_no_matches(): void
    {
        $routes = $this->registry->forModule('nonexistent');

        $this->assertSame([], $routes);
    }

    #[Test]
    public function to_route_options_returns_empty_array_when_no_routes(): void
    {
        $options = $this->registry->toRouteOptions();

        $this->assertSame([], $options);
    }
}
