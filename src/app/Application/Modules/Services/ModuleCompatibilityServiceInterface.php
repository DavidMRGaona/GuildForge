<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Domain\Modules\ValueObjects\CompatibilityResult;
use App\Domain\Modules\ValueObjects\ModuleName;

/**
 * Always evaluates module.json on disk, never the requires column, and never throws:
 * a missing or unreadable manifest is an incompatibility.
 */
interface ModuleCompatibilityServiceInterface
{
    /**
     * module.json of the installed module at {modules.path}/{name}; memoized per process
     * until the file's mtime or size changes.
     */
    public function checkInstalled(ModuleName $name): CompatibilityResult;

    /**
     * module.json at any path (staging, extracted ZIP); not memoized.
     */
    public function checkManifestFile(string $manifestPath): CompatibilityResult;

    public function checkManifest(ModuleManifestDTO $manifest): CompatibilityResult;
}
