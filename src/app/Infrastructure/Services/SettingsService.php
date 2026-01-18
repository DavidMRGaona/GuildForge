<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use Illuminate\Support\Facades\Cache;

final class SettingsService implements SettingsServiceInterface
{
    private const string CACHE_KEY = 'site_settings';
    private const int CACHE_TTL = 3600;

    /**
     * Get a single setting by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->getAllSettings();

        return $settings[$key] ?? $default;
    }

    /**
     * Set or update a setting.
     */
    public function set(string $key, string $value): void
    {
        SettingModel::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        $this->clearCache();
    }

    /**
     * Get all location settings with proper types.
     *
     * @return array{name: string, address: string, lat: float, lng: float, zoom: int}
     */
    public function getLocationSettings(): array
    {
        return [
            'name' => (string) $this->get('location_name', ''),
            'address' => (string) $this->get('location_address', ''),
            'lat' => (float) $this->get('location_lat', 0),
            'lng' => (float) $this->get('location_lng', 0),
            'zoom' => (int) $this->get('location_zoom', 10),
        ];
    }

    /**
     * Clear the settings cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get all settings from the cache or database.
     *
     * @return array<string, string|null>
     */
    private function getAllSettings(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, static function () {
            return SettingModel::all()
                ->pluck('value', 'key')
                ->toArray();
        });
    }
}
