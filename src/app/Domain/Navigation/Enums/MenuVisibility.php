<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Enums;

enum MenuVisibility: string
{
    case Public = 'public';
    case Authenticated = 'authenticated';
    case Guests = 'guests';
    case Permission = 'permission';
}
