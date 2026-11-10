<?php

declare(strict_types=1);

namespace App\Domain\Media\Enums;

enum MediaFileStatus: string
{
    case InUse = 'in_use';
    case Unused = 'unused';
    case Recent = 'recent';
    case UnknownOrigin = 'unknown_origin';

    /** Only a file that is provably unused and past the grace period may be deleted */
    public function canBeDeleted(): bool
    {
        return $this === self::Unused;
    }
}
