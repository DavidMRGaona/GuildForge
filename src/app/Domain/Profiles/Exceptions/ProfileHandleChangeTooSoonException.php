<?php

declare(strict_types=1);

namespace App\Domain\Profiles\Exceptions;

use App\Domain\Profiles\ProfileHandleChangeRule;
use DateTimeImmutable;
use DomainException;

/**
 * The owner chose an address less than ProfileHandleChangeRule::INTERVAL_DAYS days ago.
 */
final class ProfileHandleChangeTooSoonException extends DomainException
{
    private function __construct(
        public readonly DateTimeImmutable $availableAt,
    ) {
        parent::__construct(sprintf('A profile address can be chosen once every %d days.', ProfileHandleChangeRule::INTERVAL_DAYS));
    }

    public static function until(DateTimeImmutable $availableAt): self
    {
        return new self($availableAt);
    }
}
