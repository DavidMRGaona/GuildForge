<?php

declare(strict_types=1);

namespace Modules\TestModule;

use App\Modules\ModuleServiceProvider;

final class TestModuleServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'test-module';
    }
}
