<?php

declare(strict_types=1);

namespace Tests\Support\Calendar;

use App\Application\Calendar\Contracts\CalendarEntryLookupInterface;
use App\Application\Calendar\Contracts\CalendarEntrySourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;
use Throwable;

/**
 * Calendar source that can also find one entry by id, used to test the
 * per-entry iCalendar download. With `$failure` set, every lookup throws it.
 */
final readonly class FakeLookupCalendarSource implements CalendarEntryLookupInterface, CalendarEntrySourceInterface
{
    /**
     * @param  array<CalendarEntryDTO>  $entries
     */
    public function __construct(
        private array $entries = [],
        private ?Throwable $failure = null,
    ) {}

    public function sourceType(): string
    {
        return 'fake-lookup';
    }

    public function sourceLabel(): string
    {
        return 'Fake lookup source';
    }

    public function color(): CalendarSourceColor
    {
        return CalendarSourceColor::Accent;
    }

    /**
     * @return array<CalendarEntryDTO>
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->entries;
    }

    public function findEntry(string $id): ?CalendarEntryDTO
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        foreach ($this->entries as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }
}
