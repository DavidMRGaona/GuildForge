<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;

/**
 * Service for checking core application updates.
 */
interface CoreUpdateCheckerInterface
{
    /**
     * Check if a core update is available.
     */
    public function checkForUpdate(): ?GitHubReleaseInfo;

    /**
     * Get update instructions for a release.
     */
    public function getUpdateInstructions(GitHubReleaseInfo $release): string;

    /**
     * Check if this is a major version upgrade.
     */
    public function isMajorUpgrade(GitHubReleaseInfo $release): bool;
}
