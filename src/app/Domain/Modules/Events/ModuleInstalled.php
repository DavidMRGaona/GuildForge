<?php

declare(strict_types=1);

namespace App\Domain\Modules\Events;

final readonly class ModuleInstalled
{
    public function __construct(
        public string $moduleName,
        public string $moduleVersion,
        public string $modulePath,
    ) {
    }
}
