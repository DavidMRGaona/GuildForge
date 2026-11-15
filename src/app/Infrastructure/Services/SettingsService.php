<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\LocationSettingsDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use DateTimeImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Settings are cached as one entry under a key carrying a version token. A write renews the
 * token after its commit instead of forgetting a fixed key, so a read that loaded the old rows
 * before the commit can only store them under a version nobody asks for again.
 */
final class SettingsService implements SettingsServiceInterface
{
    public const string CACHE_KEY_PREFIX = 'site_settings_';

    public const string VERSION_KEY = 'site_settings_version';

    private const string VERSION_PATTERN = '/^[0-9a-f]{32}$/';

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
     * Get an encrypted setting, decrypting it on read.
     */
    public function getEncrypted(string $key, mixed $default = null): mixed
    {
        return $this->decrypt($this->get($key), $default);
    }

    /**
     * Read a setting from the table, never from the cache: for code that decides a write on it.
     */
    public function getStored(string $key, mixed $default = null): mixed
    {
        return SettingModel::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Read an encrypted setting from the table, never from the cache, decrypting it.
     */
    public function getStoredEncrypted(string $key, mixed $default = null): mixed
    {
        return $this->decrypt($this->getStored($key), $default);
    }

    /**
     * Set a setting, encrypting the value before storage.
     */
    public function setEncrypted(string $key, string $value): void
    {
        $this->set($key, Crypt::encryptString($value));
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
        // Until the commit this request reads the table: it sees its own write uncached
        app(SettingsCacheVersion::class)->pendingWrite = true;

        // After the commit: renewing earlier lets a request in between cache the old rows
        // under the new version. Runs at once outside a transaction.
        DB::afterCommit(static function (): void {
            $current = app(SettingsCacheVersion::class);
            // Until the new version is stored: if storing fails, this request reads the store again
            $current->token = null;
            $current->token = self::replaceVersion();
            $current->pendingWrite = false;
        });
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
     * Read strictly as ISO 8601 (the format the migration writes): anything else is null,
     * never a guess such as "now", so the caller can fail closed.
     */
    public function getEmailVerificationEnforcedSince(): ?DateTimeImmutable
    {
        $value = $this->get('email_verification_enforced_since');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $since = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($since === false || ($errors !== false && $errors['warning_count'] > 0)) {
            return null;
        }

        return $since;
    }

    /**
     * Check if the public site is in maintenance mode.
     */
    public function isMaintenanceModeEnabled(): bool
    {
        return $this->getBooleanSetting('maintenance_enabled', false);
    }

    /**
     * Get the message shown to visitors during maintenance, if any.
     */
    public function getMaintenanceMessage(): ?string
    {
        $message = trim((string) $this->get('maintenance_message', ''));

        return $message !== '' ? $message : null;
    }

    /**
     * Get all settings from the cache or database.
     *
     * @return array<string, string|null>
     */
    private function getAllSettings(): array
    {
        $current = app(SettingsCacheVersion::class);

        if ($current->pendingWrite) {
            return $this->loadAll();
        }

        $version = $current->token ??= $this->readVersion();

        return Cache::remember(self::CACHE_KEY_PREFIX.$version, self::CACHE_TTL, $this->loadAll(...));
    }

    /**
     * @return array<string, string|null>
     */
    private function loadAll(): array
    {
        return SettingModel::all()->pluck('value', 'key')->toArray();
    }

    /**
     * The stored version. Callers keep it in SettingsCacheVersion, so it is read once per
     * request or job.
     */
    private function readVersion(): string
    {
        $version = Cache::get(self::VERSION_KEY);

        if (! is_string($version) || preg_match(self::VERSION_PATTERN, $version) !== 1) {
            // Missing (evicted, flushed) or malformed: start a version no cached entry can match
            $version = self::newVersion();
            Cache::forever(self::VERSION_KEY, $version);
        }

        return $version;
    }

    /**
     * Stores a new version, so every entry cached under the old one is left behind (it
     * expires with its TTL).
     */
    private static function replaceVersion(): string
    {
        $version = self::newVersion();

        try {
            Cache::forever(self::VERSION_KEY, $version);

            return $version;
        } catch (Throwable $e) {
            Log::error("Settings cache could not be invalidated: {$e->getMessage()}");

            // Without a version readers start a new one, so the old entry is never served
            try {
                Cache::forget(self::VERSION_KEY);
            } catch (Throwable) {
                // Store unreachable: reads cannot use it either until it is back
            }

            // The setting is saved: the caller must still see that other requests may read the old value
            throw $e;
        }
    }

    private static function newVersion(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function decrypt(mixed $value, mixed $default): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            return $default;
        }
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
