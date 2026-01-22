<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\LocationSettingsDTO;
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
     * Get all location settings.
     */
    public function getLocationSettings(): LocationSettingsDTO
    {
        return new LocationSettingsDTO(
            name: (string) $this->get('location_name', ''),
            address: (string) $this->get('location_address', ''),
            lat: (float) $this->get('location_lat', 0),
            lng: (float) $this->get('location_lng', 0),
            zoom: (int) $this->get('location_zoom', 10),
        );
    }

    /**
     * Clear the settings cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Check if public registration is enabled.
     */
    public function isRegistrationEnabled(): bool
    {
        return $this->getBooleanSetting('auth_registration_enabled', true);
    }

    /**
     * Check if public login is enabled.
     */
    public function isLoginEnabled(): bool
    {
        return $this->getBooleanSetting('auth_login_enabled', true);
    }

    /**
     * Check if email verification is required.
     */
    public function isEmailVerificationRequired(): bool
    {
        return $this->getBooleanSetting('auth_email_verification_required', false);
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

    /**
     * Get a boolean setting value.
     */
    private function getBooleanSetting(string $key, bool $default): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return $value === '1' || $value === 'true';
    }
}
