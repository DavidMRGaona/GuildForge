<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Enums;

enum GuideAudience: string
{
    case Site = 'site';
    case Panel = 'panel';
}
