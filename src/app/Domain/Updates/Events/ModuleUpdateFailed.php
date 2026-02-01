<?php

declare(strict_types=1);

namespace App\Domain\Updates\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class ModuleUpdateFailed
{
    use Dispatchable;

    public function __construct(
        public string $moduleName,
        public string $fromVersion,
        public string $toVersion,
        public string $errorMessage,
        public bool $wasRolledBack,
    ) {}
}
