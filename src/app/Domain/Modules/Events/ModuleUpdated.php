<?php

declare(strict_types=1);

namespace App\Domain\Modules\Events;

final readonly class ModuleUpdated
{
    public function __construct(
        public string $moduleName,
        public string $previousVersion,
        public string $newVersion,
        public string $modulePath,
    ) {}
}
