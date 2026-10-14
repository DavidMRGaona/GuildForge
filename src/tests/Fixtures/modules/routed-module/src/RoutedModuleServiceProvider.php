<?php

declare(strict_types=1);

namespace Modules\RoutedModule;

use App\Modules\ModuleServiceProvider;

final class RoutedModuleServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'routed-module';
    }
}
