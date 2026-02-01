<?php

declare(strict_types=1);

namespace App\Domain\Updates\Events;

final readonly class ModuleUpdateCompleted
{
    public function __construct(
        public string $moduleName,
        public string $fromVersion,
        public string $toVersion,
    ) {
    }
}
