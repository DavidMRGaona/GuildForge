<?php

declare(strict_types=1);

namespace App\Domain\Modules\Events;

final readonly class ModuleMigrated
{
    public function __construct(
        public string $moduleId,
        public string $moduleName,
        public int $migrationsRun,
    ) {}
}
