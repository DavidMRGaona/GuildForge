<?php

declare(strict_types=1);

namespace Modules\DependentModule;

use App\Modules\ModuleServiceProvider;

final class DependentModuleServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'dependent-module';
    }
}
