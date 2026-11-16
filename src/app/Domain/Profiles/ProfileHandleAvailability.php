<?php

declare(strict_types=1);

namespace App\Domain\Profiles;

/**
 * What the editor is told about an address it offers (core#743). An address another person holds now,
 * held before, or a reserved word are all just `unavailable`: nobody learns which, so the history never
 * tells whose address it was.
 */
enum ProfileHandleAvailability: string
{
    case Available = 'available';
    /** The owner's own current address */
    case Current = 'current';
    case Unavailable = 'unavailable';
    /** Not an address at all: 3 to 30 lowercase letters, digits and single hyphens */
    case Malformed = 'malformed';
}
