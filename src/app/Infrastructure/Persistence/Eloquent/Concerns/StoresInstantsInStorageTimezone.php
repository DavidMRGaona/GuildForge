<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Concerns;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Illuminate\Support\Facades\Date;

/**
 * Eloquent formats a DateTimeInterface in the zone the object carries, without
 * converting, so an instant built in another zone would be stored with that zone's
 * wall time. The same goes for a string with an offset. A string without one is a storage
 * wall time by contract, so parsing it in the storage zone leaves it as it is.
 * Attributes cast to date are calendar dates, not instants: they keep their own day. That
 * reduction runs before any set{X}Attribute mutator, so a date-cast key with a mutator
 * receives Y-m-d.
 */
trait StoresInstantsInStorageTimezone
{
    /**
     * @param  string  $key
     * @param  mixed  $value
     */
    public function setAttribute($key, $value)
    {
        if ($this->isCalendarDateAttribute($key)) {
            $value = $this->reduceToCalendarDay($value);
        } elseif ($value instanceof DateTimeInterface && $this->isCustomFormatInstantAttribute($key)) {
            // Laravel keeps the object of a datetime:<format> cast untouched and the
            // query binds it in the zone it carries
            $value = $this->inStorageZone($value);
        } elseif (is_string($value) && trim($value) !== '' && $this->isCustomFormatInstantAttribute($key)) {
            // A string with an offset is an instant like any object; one without it is
            // parsed in the storage zone and so stays as it is
            try {
                $value = $this->inStorageZone(Date::parse($value));
            } catch (Exception) {
                // left for Laravel to reject or store as it always did
            }
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Laravel reports date:<format> casts as custom_datetime, so the raw cast string is
     * checked; datetime:<format> is an instant and does not match.
     */
    private function isCalendarDateAttribute(string $key): bool
    {
        $cast = $this->getCasts()[$key] ?? null;

        if (! is_string($cast)) {
            return false;
        }

        $type = strtolower(explode(':', $cast, 2)[0]);

        return $type === 'date' || $type === 'immutable_date';
    }

    private function isCustomFormatInstantAttribute(string $key): bool
    {
        $cast = $this->getCasts()[$key] ?? null;

        return is_string($cast)
            && str_contains($cast, ':')
            && in_array(strtolower(explode(':', $cast, 2)[0]), ['datetime', 'immutable_datetime'], true);
    }

    private function inStorageZone(DateTimeInterface $value): DateTimeInterface
    {
        $storageZone = new DateTimeZone(date_default_timezone_get());

        // DateTime::setTimezone() mutates, the immutable one already returns a copy
        return $value instanceof DateTime
            ? (clone $value)->setTimezone($storageZone)
            : $value->setTimezone($storageZone);
    }

    private function reduceToCalendarDay(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Date::parse($value)->format('Y-m-d');
            } catch (Exception) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * @param  mixed  $value
     */
    public function fromDateTime($value): mixed
    {
        if (! empty($value) && (is_string($value) || is_numeric($value))) {
            $value = $this->asDateTime($value);
        }

        if ($value instanceof DateTimeInterface) {
            $value = $this->inStorageZone($value);
        }

        return parent::fromDateTime($value);
    }
}
