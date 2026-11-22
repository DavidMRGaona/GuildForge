<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Domain\Modules\Exceptions\ModuleSettingsUnavailableException;

/**
 * Where the module settings saved in the panel live.
 *
 * A module's config/settings.php only holds its release defaults and is replaced with
 * the module directory on every update; the panel values are kept apart from it, so
 * they survive updates, image syncs and rollbacks.
 */
interface ModuleSettingsStoreInterface
{
    /**
     * Release defaults with the stored panel values on top. Never throws.
     *
     * Nested associative arrays are merged key by key; any other stored value (scalars,
     * null, lists) replaces the default whole. Default keys missing from the stored
     * values are kept, so the new keys of a release show up.
     *
     * @return array<string, mixed>
     */
    public function effective(string $moduleName, string $modulePath): array;

    /**
     * The same values as effective(), but read strictly: throws ModuleSettingsUnavailableException
     * when the stored values cannot be read, instead of falling back to the defaults. For code
     * that must not act on the defaults in place of what the site saved (a queue worker
     * refreshing its settings, core#825). Stored values that are not a JSON object still give
     * the defaults, as effective() does.
     *
     * @return array<string, mixed>
     *
     * @throws ModuleSettingsUnavailableException
     */
    public function current(string $moduleName, string $modulePath): array;

    /**
     * Release defaults only (the module's config/settings.php); [] when it ships none.
     *
     * @return array<string, mixed>
     */
    public function defaults(string $modulePath): array;

    /**
     * Stored panel values; [] when nothing is stored, the value is unreadable or the
     * store cannot be reached.
     *
     * @return array<string, mixed>
     */
    public function stored(string $moduleName): array;

    /**
     * Replace the stored panel values with the whole form state.
     *
     * @param  array<string, mixed>  $settings
     */
    public function store(string $moduleName, array $settings): void;

    /**
     * Remove the stored panel values (uninstall deleting the module data).
     */
    public function forget(string $moduleName): void;
}
