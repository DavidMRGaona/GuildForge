<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

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
