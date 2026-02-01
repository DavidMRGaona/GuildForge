<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Application\Updates\DTOs\HealthCheckResultDTO;
use App\Domain\Modules\ValueObjects\ModuleName;

/**
 * Service for checking module health after updates.
 */
interface ModuleHealthCheckerInterface
{
    /**
     * Run health checks on a module.
     */
    public function check(ModuleName $name): HealthCheckResultDTO;

    /**
     * Check if the service provider loads correctly.
     */
    public function checkProviderLoads(ModuleName $name): bool;

    /**
     * Check if module routes are responding.
     */
    public function checkRoutesRespond(ModuleName $name): bool;

    /**
     * Check if Filament resources are registered.
     */
    public function checkFilamentResources(ModuleName $name): bool;
}
