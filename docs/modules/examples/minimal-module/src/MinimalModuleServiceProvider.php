<?php

declare(strict_types=1);

namespace Modules\MinimalModule;

use App\Modules\ModuleServiceProvider;

/**
 * The parent loads routes, translations, views, migrations and policies, and
 * loads config/module.php into "modules.minimal-module".
 */
final class MinimalModuleServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'minimal-module';
    }
}
