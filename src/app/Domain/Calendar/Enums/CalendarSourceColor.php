<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Enums;

enum CalendarSourceColor: string
{
    case Primary = 'primary';
    case Accent = 'accent';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Neutral = 'neutral';
}
