<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Application\Modules\Services\ModuleRouteRegistryInterface;

final class ModuleRouteRegistry implements ModuleRouteRegistryInterface
{
    /** @var array<ModuleRouteDTO> */
    private array $routes = [];

    public function register(ModuleRouteDTO $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * @param  array<ModuleRouteDTO>  $routes
     */
    public function registerMany(array $routes): void
    {
        foreach ($routes as $route) {
            $this->register($route);
        }
    }

    /**
     * @return array<ModuleRouteDTO>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * @return array<ModuleRouteDTO>
     */
    public function forModule(string $moduleName): array
    {
        return array_values(
            array_filter($this->routes, fn (ModuleRouteDTO $route): bool => $route->module === $moduleName)
        );
    }

    /**
     * @return array<string, string>
     */
    public function toRouteOptions(): array
    {
        $options = [];
        foreach ($this->routes as $route) {
            $options[$route->routeName] = $route->label;
        }

        return $options;
    }

    public function unregisterModule(string $moduleName): void
    {
        $this->routes = array_values(
            array_filter($this->routes, fn (ModuleRouteDTO $route): bool => $route->module !== $moduleName)
        );
    }

    public function clear(): void
    {
        $this->routes = [];
    }
}
