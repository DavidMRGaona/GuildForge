<?php

declare(strict_types=1);

namespace App\Application\Publications\Services;

use App\Application\Publications\Contracts\PublicationSourceInterface;

interface PublicationSourceRegistryInterface
{
    /**
     * Register a publication source class, optionally scoped to a module.
     *
     * @param  class-string  $sourceClass
     */
    public function register(string $sourceClass, ?string $module = null): void;

    /**
     * Register multiple publication source classes, optionally scoped to a module.
     *
     * @param  array<class-string>  $sourceClasses
     */
    public function registerMany(array $sourceClasses, ?string $module = null): void;

    /**
     * Get all registered publication source classes (unique class-strings).
     *
     * @return array<class-string>
     */
    public function all(): array;

    /**
     * Resolve the registered sources, keyed by type, in registration order.
     *
     * A class that cannot be resolved, is not a PublicationSourceInterface or repeats a
     * type already taken is skipped and logged: the first source registered for a type wins.
     *
     * @return array<string, PublicationSourceInterface>
     */
    public function sources(): array;

    /**
     * The source offering the given type, or null when none does.
     */
    public function source(string $type): ?PublicationSourceInterface;

    /**
     * Unregister all publication source classes for a module.
     */
    public function unregisterModule(string $module): void;

    /**
     * Clear all registered publication source classes.
     */
    public function clear(): void;
}
