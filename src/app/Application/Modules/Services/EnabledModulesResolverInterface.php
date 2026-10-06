<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\RejectedModuleDTO;
use App\Domain\Modules\Entities\Module;

/**
 * The only answer to "which modules does this process load": enabled in the database
 * and compatible according to their module.json on disk. Never writes module state.
 */
interface EnabledModulesResolverInterface
{
    /**
     * @return list<string>
     */
    public function names(): array;

    /**
     * @return list<Module>
     */
    public function modules(): array;

    /**
     * Enabled modules that were not loaded because they are not compatible.
     *
     * @return list<RejectedModuleDTO>
     */
    public function rejected(): array;

    /**
     * database/migrations directories of every module on disk whose manifest is compatible,
     * whatever its status and even without a modules table (migrate:fresh, module test suites).
     *
     * @return list<string>
     */
    public function migrationPaths(): array;

    public function reset(): void;
}
