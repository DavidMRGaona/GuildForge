<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Domain\Modules\Exceptions\ModuleMigrationViolationException;

interface ModuleMigrationAnalyzerInterface
{
    /**
     * @throws ModuleMigrationViolationException
     */
    public function analyzeMigrations(string $moduleName, string $migrationsPath): void;

    /**
     * @throws ModuleMigrationViolationException
     */
    public function analyzeSeeders(string $moduleName, string $seedersPath): void;
}
