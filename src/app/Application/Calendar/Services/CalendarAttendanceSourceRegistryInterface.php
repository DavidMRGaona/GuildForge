<?php

declare(strict_types=1);

namespace App\Application\Calendar\Services;

use App\Application\Calendar\Contracts\CalendarAttendanceSourceInterface;

interface CalendarAttendanceSourceRegistryInterface
{
    /**
     * Register a calendar attendance source class, optionally scoped to a module.
     *
     * @param  class-string<CalendarAttendanceSourceInterface>  $sourceClass
     */
    public function register(string $sourceClass, ?string $module = null): void;

    /**
     * Register multiple calendar attendance source classes, optionally scoped to a module.
     *
     * @param  array<class-string<CalendarAttendanceSourceInterface>>  $sourceClasses
     */
    public function registerMany(array $sourceClasses, ?string $module = null): void;

    /**
     * All registered calendar attendance source classes (unique class-strings).
     *
     * @return array<class-string<CalendarAttendanceSourceInterface>>
     */
    public function all(): array;

    /**
     * Unregister all calendar attendance source classes of a module.
     */
    public function unregisterModule(string $module): void;

    /**
     * Clear all registered calendar attendance source classes.
     */
    public function clear(): void;
}
