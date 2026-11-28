<?php

declare(strict_types=1);

namespace App\Domain\Profiles\Exceptions;

use RuntimeException;

/**
 * The public profiles of a list of accounts could not be read (core#871): the database, the profile rows
 * or a row the profile page would not answer failed. It carries no cause and no detail, since a message
 * may hold what a person can see; an empty list is never given instead, because it would be taken for
 * «nobody is visible».
 */
final class VisibleProfilesUnavailableException extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self('The visible profiles of a list of accounts could not be read.');
    }
}
