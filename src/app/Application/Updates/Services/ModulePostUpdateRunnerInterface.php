<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Updates\Exceptions\UpdateException;

/**
 * Runs what has to happen after a module's new files are in place (migrations,
 * seeders, health check, cache refresh) in a process that only knows the new code.
 */
interface ModulePostUpdateRunnerInterface
{
    /**
     * @throws UpdateException With the reported error when any step fails
     */
    public function run(ModuleName $moduleName): void;
}
