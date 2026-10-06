<?php

declare(strict_types=1);

namespace Modules\IncompatibleModule;

use App\Modules\ModuleServiceProvider;

// Records that this file was compiled: the compatibility gate must never let that happen
$GLOBALS['incompatible_module_compiled'][] = __FILE__;
$marker = getenv('INCOMPATIBLE_MODULE_MARKER');
if (is_string($marker) && $marker !== '') {
    file_put_contents($marker, __FILE__.PHP_EOL, FILE_APPEND);
}

final class IncompatibleModuleServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'incompatible-module';
    }
}
