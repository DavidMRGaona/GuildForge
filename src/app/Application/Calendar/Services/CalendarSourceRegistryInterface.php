<?php

declare(strict_types=1);

namespace App\Application\Calendar\Services;

interface CalendarSourceRegistryInterface
{
    /**
     * Register a calendar source class, optionally scoped to a module.
     *
     * @param  class-string  $sourceClass
     */
    public function register(string $sourceClass, ?string $module = null): void;

    /**
     * Register multiple calendar source classes, optionally scoped to a module.
     *
     * @param  array<class-string>  $sourceClasses
     */
    public function registerMany(array $sourceClasses, ?string $module = null): void;

    /**
     * Get all registered calendar source classes (unique class-strings).
     *
     * @return array<class-string>
     */
    public function all(): array;

    /**
     * Unregister all calendar source classes for a module.
     */
    public function unregisterModule(string $module): void;

    /**
     * Clear all registered calendar source classes.
     */
    public function clear(): void;
}
