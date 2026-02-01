<?php

declare(strict_types=1);

namespace App\Domain\Updates\Events;

final readonly class ModuleUpdateFailed
{
    public function __construct(
        public string $moduleName,
        public string $fromVersion,
        public string $toVersion,
        public string $errorMessage,
        public bool $wasRolledBack,
    ) {}
}
