<?php

declare(strict_types=1);

namespace App\Application\Navigation\Services;

/**
 * Registry of available routes with user-friendly labels for menu configuration.
 */
interface RouteRegistryInterface
{
    /**
     * Get all available routes with user-friendly labels.
     *
     * @return array<string, string> [route_name => label]
     */
    public function getAvailableRoutes(): array;
}
