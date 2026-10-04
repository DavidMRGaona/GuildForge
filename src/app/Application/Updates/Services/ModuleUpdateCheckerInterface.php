<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Application\Updates\DTOs\AvailableUpdateDTO;
use App\Application\Updates\DTOs\UpdateCheckResultDTO;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Support\Collection;

/**
 * Service for checking module updates from GitHub.
 */
interface ModuleUpdateCheckerInterface
{
    /**
     * Check for updates for a specific module.
     */
    public function checkForUpdate(ModuleName $name): ?AvailableUpdateDTO;

    /**
     * Check for updates for all modules with configured sources.
     *
     * @return Collection<int, AvailableUpdateDTO>
     */
    public function checkAllForUpdates(): Collection;

    /**
     * Check every module with a configured repository, reporting per-module errors
     * and the modules that have no repository instead of silently skipping them.
     *
     * @param  bool  $fresh  Bypass cached GitHub responses (an explicit "check now")
     */
    public function checkAll(bool $fresh = false): UpdateCheckResultDTO;

    /**
     * Get the last check timestamp for a module.
     */
    public function getLastCheckTime(ModuleName $name): ?\DateTimeImmutable;

    /**
     * Force refresh update check, ignoring cache.
     */
    public function forceCheck(ModuleName $name): ?AvailableUpdateDTO;
}
