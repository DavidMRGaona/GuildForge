<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Application\Updates\DTOs\PostUpdateReportDTO;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Exceptions\UpdateException;

/**
 * Runs what has to happen after a module's new files are in place, in a process
 * that only knows the new code.
 */
interface ModulePostUpdateRunnerInterface
{
    /**
     * Health check, then migrations, seeders and the new version in one database
     * transaction. Returning means it was committed; throwing means nothing was.
     *
     * @throws UpdateException With the reported error when any step fails
     */
    public function run(ModuleName $moduleName, ModuleVersion $version): PostUpdateReportDTO;

    /**
     * Rebuild config, route and view caches. Best effort: failures are logged, never thrown,
     * because the update is already committed when this runs.
     */
    public function refreshCaches(): void;
}
