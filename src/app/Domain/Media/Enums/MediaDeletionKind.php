<?php

declare(strict_types=1);

namespace App\Domain\Media\Enums;

/**
 * How a file left the media store: deleted by hand from the library, or released when a record let go of it.
 */
enum MediaDeletionKind: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
}
