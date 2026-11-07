<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Services;

use App\Application\Calendar\Contracts\CalendarAttendanceSourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\Calendar\Services\CalendarAttendanceSourceRegistryInterface;
use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use DateTimeImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Intersects what the attendance sources say a person takes part in with what the
 * public calendar shows in the same range, so the personal calendar never shows
 * more than the public one: a hidden, cancelled or unknown entry is simply not
 * there to match. One public calendar read per call, whatever the number of
 * references.
 */
final readonly class PersonalCalendarService implements PersonalCalendarServiceInterface
{
    public function __construct(
        private Container $container,
        private CalendarAttendanceSourceRegistryInterface $registry,
        private CalendarAggregatorServiceInterface $calendar,
    ) {}

    public function isAvailable(): bool
    {
        return $this->registry->all() !== [];
    }

    /**
     * @return array<CalendarEntryDTO>
     */
    public function entriesFor(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $attended = $this->attendedKeys($userId, $from, $to);

        if ($attended === []) {
            return [];
        }

        return array_values(array_filter(
            $this->calendar->findByDateRange($from, $to),
            static fn (CalendarEntryDTO $entry): bool => isset(
                $attended[(new CalendarEntryReferenceDTO($entry->sourceType, $entry->id))->key()]
            ),
        ));
    }

    /**
     * Keys of every entry the sources reference, without duplicates.
     *
     * @return array<string, true>
     */
    private function attendedKeys(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $keys = [];

        foreach ($this->registry->all() as $sourceClass) {
            foreach ($this->safeAttendedEntries($sourceClass, $userId, $from, $to) as $reference) {
                if ($reference instanceof CalendarEntryReferenceDTO) {
                    $keys[$reference->key()] = true;
                }
            }
        }

        return $keys;
    }

    /**
     * @param  class-string<CalendarAttendanceSourceInterface>  $sourceClass
     * @return array<mixed>
     */
    private function safeAttendedEntries(
        string $sourceClass,
        string $userId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        try {
            $source = $this->container->make($sourceClass);
        } catch (Throwable $exception) {
            Log::warning('[PersonalCalendarService] Failed to resolve calendar attendance source', [
                'source' => $sourceClass,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! $source instanceof CalendarAttendanceSourceInterface) {
            Log::warning('[PersonalCalendarService] Registered class is not a calendar attendance source', [
                'source' => $sourceClass,
            ]);

            return [];
        }

        try {
            return $source->attendedEntries($userId, $from, $to);
        } catch (Throwable $exception) {
            Log::warning('[PersonalCalendarService] Calendar attendance source failed to provide entries', [
                'source' => $source::class,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
