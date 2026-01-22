<?php

declare(strict_types=1);

namespace App\Domain\Modules\Enums;

enum ModuleStatus: string
{
    case Disabled = 'disabled';
    case Enabled = 'enabled';
}
