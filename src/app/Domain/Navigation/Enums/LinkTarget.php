<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Enums;

enum LinkTarget: string
{
    case Self = '_self';
    case Blank = '_blank';
}
