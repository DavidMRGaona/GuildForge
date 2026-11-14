<?php

declare(strict_types=1);

namespace App\Application\Linking\Services;

use App\Application\Linking\Contracts\LinkableRecordSourceInterface;

interface LinkableRecordSourceRegistryInterface
{
    /**
     * Register a linkable record source class, optionally scoped to a module.
     *
     * @param  class-string  $sourceClass
     */
    public function register(string $sourceClass, ?string $module = null): void;

    /**
     * Register multiple linkable record source classes, optionally scoped to a module.
     *
     * @param  array<class-string>  $sourceClasses
     */
    public function registerMany(array $sourceClasses, ?string $module = null): void;

    /**
     * Get all registered linkable record source classes (unique class-strings).
     *
     * @return array<class-string>
     */
    public function all(): array;

    /**
     * Resolve the registered sources, keyed by type, in registration order.
     *
     * A class that cannot be resolved, is not a LinkableRecordSourceInterface, cannot tell its
     * type or repeats a type already taken is skipped and logged: the first source registered
     * for a type wins. Only enabled modules register sources, so a disabled or uninstalled
     * module's types have no source.
     *
     * The sources are built once per request or queued job and kept until it ends or the
     * registrations change, so a source that cannot be used is logged once, not on every call.
     *
     * @return array<string, LinkableRecordSourceInterface>
     */
    public function sources(): array;

    /**
     * The source offering the given type, or null when none does.
     */
    public function source(string $type): ?LinkableRecordSourceInterface;

    /**
     * Unregister all linkable record source classes for a module.
     */
    public function unregisterModule(string $module): void;

    /**
     * Unregister every module's linkable record source classes. Those the core registered
     * itself (without a module, like its events) stay: the core offers them whatever modules
     * are enabled.
     */
    public function clear(): void;
}
