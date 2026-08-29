<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Services;

use App\Application\Calendar\Contracts\CalendarEntrySourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\Calendar\Services\CalendarSourceRegistryInterface;
use App\Infrastructure\Calendar\Sources\EventCalendarSource;
use DateTimeImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CalendarAggregatorService implements CalendarAggregatorServiceInterface
{
    public function __construct(
        private readonly Container $container,
        private readonly CalendarSourceRegistryInterface $sourceRegistry,
    ) {}

    /**
     * @return array<CalendarEntryDTO>
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $entries = [];

        foreach ($this->sources() as $source) {
            $entries = [...$entries, ...$this->safeFindByDateRange($source, $from, $to)];
        }

        usort($entries, static fn (CalendarEntryDTO $a, CalendarEntryDTO $b): int => $a->start <=> $b->start);

        return $entries;
    }

    /**
     * @return array<CalendarEntrySourceInterface>
     */
    private function sources(): array
    {
        $sources = [$this->container->make(EventCalendarSource::class)];

        foreach ($this->sourceRegistry->all() as $sourceClass) {
            try {
                $sources[] = $this->container->make($sourceClass);
            } catch (Throwable $exception) {
                Log::warning('[CalendarAggregatorService] Failed to resolve calendar source', [
                    'source' => $sourceClass,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sources;
    }

    /**
     * @return array<CalendarEntryDTO>
     */
    private function safeFindByDateRange(
        CalendarEntrySourceInterface $source,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        try {
            return $source->findByDateRange($from, $to);
        } catch (Throwable $exception) {
            Log::warning('[CalendarAggregatorService] Calendar source failed to provide entries', [
                'source' => $source::class,
                'sourceType' => $this->safeSourceType($source),
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function safeSourceType(CalendarEntrySourceInterface $source): string
    {
        try {
            return $source->sourceType();
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
