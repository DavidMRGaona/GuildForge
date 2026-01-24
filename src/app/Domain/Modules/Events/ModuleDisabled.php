<?php

declare(strict_types=1);

namespace App\Domain\Modules\Events;

final readonly class ModuleDisabled
{
    public function __construct(
        public string $moduleId,
        public string $moduleName,
    ) {}
}
