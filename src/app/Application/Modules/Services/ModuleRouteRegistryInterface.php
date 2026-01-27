<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\ModuleRouteDTO;

interface ModuleRouteRegistryInterface
{
    /**
     * Register a route from a module.
     */
    public function register(ModuleRouteDTO $route): void;

    /**
     * Register multiple routes from a module.
     *
     * @param  array<ModuleRouteDTO>  $routes
     */
    public function registerMany(array $routes): void;

    /**
     * Get all registered routes.
     *
     * @return array<ModuleRouteDTO>
     */
    public function all(): array;

    /**
     * Get routes for a specific module.
     *
     * @return array<ModuleRouteDTO>
     */
    public function forModule(string $moduleName): array;

    /**
     * Convert registry to route options format for Select fields.
     *
     * @return array<string, string>
     */
    public function toRouteOptions(): array;

    /**
     * Unregister all routes for a module.
     */
    public function unregisterModule(string $moduleName): void;

    /**
     * Clear all registered routes.
     */
    public function clear(): void;
}
