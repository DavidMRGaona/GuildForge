<?php

declare(strict_types=1);

namespace App\Application\Calendar\DTOs;

/**
 * Points at one public calendar entry: the source type and the id that source
 * gives the entry in `CalendarEntryDTO::$id` (`('event', $eventId)`,
 * `('game-table', $tableId)`...).
 */
final readonly class CalendarEntryReferenceDTO
{
    public function __construct(
        public string $sourceType,
        public string $id,
    ) {}

    /**
     * Same value for two references to the same entry.
     */
    public function key(): string
    {
        return $this->sourceType."\0".$this->id;
    }
}
