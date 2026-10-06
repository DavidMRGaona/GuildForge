<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Domain\Modules\ValueObjects\ModuleVersion;

/**
 * Service for managing core application version.
 */
interface CoreVersionServiceInterface
{
    /**
     * Get the current core version from VERSION file.
     *
     * Never throws: a missing or malformed file yields 0.0.0 (a malformed one is logged as an
     * error), which no module's core constraint accepts, so modules are blocked visibly.
     */
    public function getCurrentVersion(): ModuleVersion;

    /**
     * Get the current git commit hash.
     */
    public function getCurrentCommit(): string;

    /**
     * Check if a version string satisfies a constraint.
     */
    public function satisfies(string $constraint): bool;
}
