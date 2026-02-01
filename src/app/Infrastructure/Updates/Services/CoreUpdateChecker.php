<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;

final readonly class CoreUpdateChecker implements CoreUpdateCheckerInterface
{
    public function __construct(
        private CoreVersionServiceInterface $versionService,
        private GitHubReleaseFetcherInterface $githubFetcher,
    ) {
    }

    public function checkForUpdate(): ?GitHubReleaseInfo
    {
        $owner = config('updates.core.owner');
        $repo = config('updates.core.repo');

        if ($owner === null || $repo === null) {
            return null;
        }

        $release = $this->githubFetcher->getLatestRelease($owner, $repo);

        if ($release === null) {
            return null;
        }

        // Skip prereleases unless configured
        if ($release->isPrerelease && ! config('updates.behavior.allow_prereleases', false)) {
            return null;
        }

        $currentVersion = $this->versionService->getCurrentVersion();

        if (! $release->version->isGreaterThan($currentVersion)) {
            return null;
        }

        return $release;
    }

    public function getUpdateInstructions(GitHubReleaseInfo $release): string
    {
        $version = $release->version->value();
        $currentCommit = $this->versionService->getCurrentCommit();

        return <<<INSTRUCTIONS
        # Core update to v{$version}

        ## Antes de actualizar
        1. Crear un backup de la base de datos
        2. Verificar que no hay trabajos pendientes en la cola

        ## Instrucciones de actualización

        ```bash
        php artisan down --refresh=15

        git fetch origin
        git checkout tags/v{$version}

        composer install --no-dev --optimize-autoloader
        npm ci && npm run build

        php artisan migrate --force
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache

        php artisan up
        ```

        ## Rollback (si es necesario)

        ```bash
        git checkout {$currentCommit}
        composer install --no-dev --optimize-autoloader
        npm ci && npm run build
        ```

        ## Notas de la versión

        {$release->releaseNotes}
        INSTRUCTIONS;
    }

    public function isMajorUpgrade(GitHubReleaseInfo $release): bool
    {
        $currentVersion = $this->versionService->getCurrentVersion();

        return $release->isMajorUpgradeFrom($currentVersion);
    }
}
