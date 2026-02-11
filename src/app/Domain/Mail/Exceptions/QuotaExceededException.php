<?php

declare(strict_types=1);

namespace App\Domain\Mail\Exceptions;

use DomainException;

final class QuotaExceededException extends DomainException
{
    public static function dailyLimitReached(int $limit): self
    {
        return new self("Daily email quota of {$limit} has been reached.");
    }

    public static function monthlyLimitReached(int $limit): self
    {
        return new self("Monthly email quota of {$limit} has been reached.");
    }
}
