<?php

declare(strict_types=1);

namespace Modules\CircularB;

use App\Modules\ModuleServiceProvider;

final class CircularBServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'circular-b';
    }
}
