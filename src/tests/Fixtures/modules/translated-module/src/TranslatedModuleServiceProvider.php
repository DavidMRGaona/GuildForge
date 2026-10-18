<?php

declare(strict_types=1);

namespace Modules\TranslatedModule;

use App\Modules\ModuleServiceProvider;

final class TranslatedModuleServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'translated-module';
    }
}
