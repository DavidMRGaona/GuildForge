<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Services;

use App\Application\Calendar\Contracts\CalendarAttendanceSourceInterface;
use App\Application\Calendar\Services\CalendarAttendanceSourceRegistryInterface;

final class CalendarAttendanceSourceRegistry implements CalendarAttendanceSourceRegistryInterface
{
    /** @var array<class-string<CalendarAttendanceSourceInterface>, ?string> Map of attendance source class to owning module (null for core). */
    private array $sources = [];

    public function register(string $sourceClass, ?string $module = null): void
    {
        $this->sources[$sourceClass] = $module;
    }

    /**
     * @param  array<class-string<CalendarAttendanceSourceInterface>>  $sourceClasses
     */
    public function registerMany(array $sourceClasses, ?string $module = null): void
    {
        foreach ($sourceClasses as $sourceClass) {
            $this->register($sourceClass, $module);
        }
    }

    /**
     * @return array<class-string<CalendarAttendanceSourceInterface>>
     */
    public function all(): array
    {
        return array_keys($this->sources);
    }

    public function unregisterModule(string $module): void
    {
        $this->sources = array_filter(
            $this->sources,
            fn (?string $sourceModule): bool => $sourceModule !== $module
        );
    }

    public function clear(): void
    {
        $this->sources = [];
    }
}
