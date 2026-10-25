<?php

declare(strict_types=1);

namespace App\Application\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

interface TimezoneServiceInterface
{
    /** Zone of everything people see and type. */
    public function displayTimezone(): DateTimeZone;

    /** Zone the datetime columns are stored in. */
    public function storageTimezone(): DateTimeZone;

    public function toDisplay(DateTimeInterface $instant): DateTimeImmutable;

    public function toStorage(DateTimeInterface $instant): DateTimeImmutable;

    /**
     * Reads a wall time typed in the display zone and returns the instant in the storage zone.
     *
     * Accepts `Y-m-d H:i`, `Y-m-d H:i:s`, `Y-m-d\TH:i` and `Y-m-d\TH:i:s`.
     *
     * @throws InvalidArgumentException
     */
    public function fromDisplayWallTime(string $wallTime): DateTimeImmutable;

    public function toDisplayWallTime(DateTimeInterface $instant, string $format = 'Y-m-d\TH:i'): string;

    /** Midnight of the current display day, in the display zone. */
    public function today(): DateTimeImmutable;

    /**
     * `[start, end)` of a display calendar day, as storage-zone instants.
     *
     * @param  DateTimeInterface|string  $day  a string must be `Y-m-d`
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     *
     * @throws InvalidArgumentException
     */
    public function displayDayRange(DateTimeInterface|string $day): array;

    /**
     * `[start, end)` of the display month containing the given instant, as storage-zone instants.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public function displayMonthRange(DateTimeInterface $dayInMonth): array;

    /** Formats an instant in the display zone. */
    public function format(DateTimeInterface $instant, string $format = 'd/m/Y H:i'): string;

    /**
     * ISO 8601 string for the browser and the API: always in the display zone, so the JSON
     * does not change when the storage zone does.
     */
    public function iso(?DateTimeInterface $instant): ?string;
}
