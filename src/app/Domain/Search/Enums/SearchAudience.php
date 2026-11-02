<?php

declare(strict_types=1);

namespace App\Domain\Search\Enums;

enum SearchAudience: string
{
    case Public = 'public';
    case Authenticated = 'authenticated';
    case Members = 'members';
}
