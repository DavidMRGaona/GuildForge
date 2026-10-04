<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Domain\Modules\ValueObjects\ModuleVersion;

/**
 * Decides whether prerelease versions are candidate updates for an installed module.
 *
 * A module that is already on a prerelease (e.g. 1.0.8-beta) follows that channel,
 * so its next beta counts as an update; stable installs only get prereleases when
 * the site opts in via updates.behavior.allow_prereleases.
 */
final readonly class ReleaseChannelPolicy
{
    public function __construct(
        private bool $allowPrereleases,
    ) {}

    public function includesPrereleasesFor(ModuleVersion $installed): bool
    {
        return $this->allowPrereleases || $installed->preRelease !== null;
    }
}
