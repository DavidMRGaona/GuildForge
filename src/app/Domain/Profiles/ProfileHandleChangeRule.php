<?php

declare(strict_types=1);

namespace App\Domain\Profiles;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * How often the owner may choose another address for their profile (core#743): once every 30 days,
 * counted from the last time they chose one. Moves that follow the public name, before any choice, do
 * not count. Checked on the server whatever the editor shows.
 */
final class ProfileHandleChangeRule
{
    public const int INTERVAL_DAYS = 30;

    /**
     * @param  DateTimeInterface|null  $lastChosenAt  when the owner last chose an address; null when never
     */
    public static function allows(?DateTimeInterface $lastChosenAt, DateTimeInterface $now): bool
    {
        return ! self::nextChangeAt($lastChosenAt, $now) instanceof DateTimeImmutable;
    }

    /**
     * When the owner may choose again, or null when they may now.
     */
    public static function nextChangeAt(?DateTimeInterface $lastChosenAt, DateTimeInterface $now): ?DateTimeImmutable
    {
        if (! $lastChosenAt instanceof DateTimeInterface) {
            return null;
        }

        $next = DateTimeImmutable::createFromInterface($lastChosenAt)->modify('+'.self::INTERVAL_DAYS.' days');

        return $next > DateTimeImmutable::createFromInterface($now) ? $next : null;
    }
}
