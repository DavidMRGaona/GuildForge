<?php

declare(strict_types=1);

namespace Tests\Support\Modules;

use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;

trait RegistersBootedModuleProviders
{
    private function loadProvider(ModuleServiceProvider $provider): void
    {
        // As if the module had booted: the page asks the loader for its provider
        (function (ModuleServiceProvider $provider): void {
            $this->loadedProviders[$provider->moduleName()] = $provider;
        })->call(app(ModuleLoader::class), $provider);
    }
}
