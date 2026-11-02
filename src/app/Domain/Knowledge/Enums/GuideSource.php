<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Enums;

enum GuideSource: string
{
    case Core = 'core';
    case Module = 'module';
    case Site = 'site';
}
