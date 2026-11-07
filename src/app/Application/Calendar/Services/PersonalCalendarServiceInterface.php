<?php

declare(strict_types=1);

namespace App\Application\Calendar\Services;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use DateTimeImmutable;

/**
 * A person's own calendar: the public calendar entries they take part in, as the
 * registered calendar attendance sources report them.
 */
interface PersonalCalendarServiceInterface
{
    /**
     * Whether any attendance source is registered. Without one, nobody's
     * personal calendar can have anything in it.
     */
    public function isAvailable(): bool;

    /**
     * The entries of the public calendar in [from, to) the person takes part in,
     * sorted by start. A failing source is skipped and logged; references the
     * public calendar does not show in the range are dropped.
     *
     * @return array<CalendarEntryDTO>
     */
    public function entriesFor(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
