<?php

declare(strict_types=1);

namespace Modules\MinimalModule;

use App\Modules\ModuleServiceProvider;

final class MinimalModuleServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'minimal-module';
    }

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            $this->modulePath('config/module.php'),
            'minimal_module'
        );
    }

    public function boot(): void
    {
        parent::boot();
    }
}
