<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

final class InvalidEventDatesException extends DomainException
{
    public static function endDateBeforeStartDate(): self
    {
        return new self('Event end date cannot be before start date.');
    }
}
