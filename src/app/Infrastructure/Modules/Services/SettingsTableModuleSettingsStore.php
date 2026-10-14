<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the panel values of each module as JSON in the settings table, under
 * "module_settings:<name>" (the key venue-bookings already reads and writes).
 */
final readonly class SettingsTableModuleSettingsStore implements ModuleSettingsStoreInterface
{
    public const string KEY_PREFIX = 'module_settings:';

    public function __construct(
        private SettingsServiceInterface $settings,
    ) {}

    public function effective(string $moduleName, string $modulePath): array
    {
        return self::merge($this->defaults($modulePath), $this->stored($moduleName));
    }

    public function defaults(string $modulePath): array
    {
        $file = rtrim($modulePath, '/').'/config/settings.php';

        if (! is_file($file)) {
            return [];
        }

        try {
            $defaults = (static fn (string $path): mixed => require $path)($file);
        } catch (Throwable $e) {
            Log::warning("Module settings defaults could not be read: {$e->getMessage()}", ['file' => $file]);

            return [];
        }

        return is_array($defaults) ? $defaults : [];
    }

    public function stored(string $moduleName): array
    {
        try {
            $value = $this->settings->get(self::KEY_PREFIX.$moduleName);
        } catch (Throwable $e) {
            // Database or cache unreachable (fresh install, config:cache without a database)
            Log::warning("Stored settings of module {$moduleName} could not be read: {$e->getMessage()}");

            return [];
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        // Settings are a JSON object: a list or a scalar is not something to merge
        if (! is_array($decoded) || ($decoded !== [] && ! self::isMap($decoded))) {
            Log::warning("Stored settings of module {$moduleName} are not a JSON object; using the defaults");

            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public function store(string $moduleName, array $settings): void
    {
        $this->settings->set(self::KEY_PREFIX.$moduleName, json_encode($settings, JSON_THROW_ON_ERROR));
    }

    public function forget(string $moduleName): void
    {
        SettingModel::query()->where('key', self::KEY_PREFIX.$moduleName)->delete();

        $this->settings->clearCache();
    }

    /**
     * Stored values win; nested associative arrays are merged, anything else (lists
     * included) replaces the default whole, never merged by index.
     *
     * @param  array<array-key, mixed>  $defaults
     * @param  array<array-key, mixed>  $stored
     * @return array<array-key, mixed>
     */
    private static function merge(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            $default = $defaults[$key] ?? null;

            $defaults[$key] = self::isMap($value) && self::isMap($default)
                ? self::merge($default, $value)
                : $value;
        }

        return $defaults;
    }

    /**
     * A non-empty array whose keys are all strings; integer keys come from lists and from
     * fields saved by index, which replace the default whole.
     *
     * @phpstan-assert-if-true array<string, mixed> $value
     */
    private static function isMap(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
