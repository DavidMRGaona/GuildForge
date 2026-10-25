<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final readonly class TimezoneService implements TimezoneServiceInterface
{
    private const array WALL_TIME_FORMATS = ['Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'];

    public function __construct(
        private string $displayTimezone,
    ) {}

    public function displayTimezone(): DateTimeZone
    {
        return new DateTimeZone($this->displayTimezone);
    }

    public function storageTimezone(): DateTimeZone
    {
        // Read on every call: the storage zone can change after boot (marker written, apply() run again).
        return new DateTimeZone(date_default_timezone_get());
    }

    public function toDisplay(DateTimeInterface $instant): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($instant)->setTimezone($this->displayTimezone());
    }

    public function toStorage(DateTimeInterface $instant): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($instant)->setTimezone($this->storageTimezone());
    }

    public function fromDisplayWallTime(string $wallTime): DateTimeImmutable
    {
        foreach (self::WALL_TIME_FORMATS as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$format, $wallTime, $this->displayTimezone());

            if ($parsed === false) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            // A date such as 2026-02-30 parses with a warning and rolls over; refuse it.
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            // Strict spelling (two-digit fields, single separators): the wall time must read back as
            // typed. Checked in UTC, which has no skipped hour, so a changeover time is not rejected.
            $reread = DateTimeImmutable::createFromFormat('!'.$format, $wallTime, new DateTimeZone('UTC'));

            if ($reread === false || $reread->format($format) !== $wallTime) {
                continue;
            }

            return $this->toStorage($parsed);
        }

        throw new InvalidArgumentException(sprintf('Invalid wall time "%s".', $wallTime));
    }

    public function toDisplayWallTime(DateTimeInterface $instant, string $format = 'Y-m-d\TH:i'): string
    {
        return $this->toDisplay($instant)->format($format);
    }

    public function today(): DateTimeImmutable
    {
        return CarbonImmutable::now($this->displayTimezone())->setTime(0, 0)->toDateTimeImmutable();
    }

    public function displayDayRange(DateTimeInterface|string $day): array
    {
        if (is_string($day)) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day, $this->displayTimezone());
            $errors = DateTimeImmutable::getLastErrors();

            if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new InvalidArgumentException(sprintf('Invalid day "%s", expected Y-m-d.', $day));
            }

            $start = $parsed;
        } else {
            $start = $this->toDisplay($day)->setTime(0, 0);
        }

        return [$this->toStorage($start), $this->toStorage($start->modify('+1 day'))];
    }

    public function displayMonthRange(DateTimeInterface $dayInMonth): array
    {
        $start = $this->toDisplay($dayInMonth)->modify('first day of this month')->setTime(0, 0);

        return [$this->toStorage($start), $this->toStorage($start->modify('first day of next month'))];
    }

    public function format(DateTimeInterface $instant, string $format = 'd/m/Y H:i'): string
    {
        return $this->toDisplay($instant)->format($format);
    }

    public function iso(?DateTimeInterface $instant): ?string
    {
        return $instant instanceof DateTimeInterface ? $this->format($instant, 'c') : null;
    }
}
