<?php

declare(strict_types=1);

namespace Modules\CircularA;

use App\Modules\ModuleServiceProvider;

final class CircularAServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'circular-a';
    }
}
