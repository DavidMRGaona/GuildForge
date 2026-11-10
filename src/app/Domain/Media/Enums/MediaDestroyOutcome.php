<?php

declare(strict_types=1);

namespace App\Domain\Media\Enums;

enum MediaDestroyOutcome: string
{
    case Deleted = 'deleted';
    case Missing = 'missing';
}
