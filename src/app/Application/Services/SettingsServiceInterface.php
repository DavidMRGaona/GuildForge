<?php

declare(strict_types=1);

namespace App\Application\Services;

interface SettingsServiceInterface
{
    /**
     * Get a single setting by key.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set or update a setting.
     */
    public function set(string $key, string $value): void;

    /**
     * Get all location settings with proper types.
     *
     * @return array{name: string, address: string, lat: float, lng: float, zoom: int}
     */
    public function getLocationSettings(): array;

    /**
     * Clear the settings cache.
     */
    public function clearCache(): void;
}
