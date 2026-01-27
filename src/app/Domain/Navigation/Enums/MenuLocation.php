<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Enums;

enum MenuLocation: string
{
    case Header = 'header';
    case Footer = 'footer';
}
