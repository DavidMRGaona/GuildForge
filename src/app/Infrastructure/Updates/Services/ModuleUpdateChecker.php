<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Updates\DTOs\AvailableUpdateDTO;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final readonly class ModuleUpdateChecker implements ModuleUpdateCheckerInterface
{
    public function __construct(
        private ModuleRepositoryInterface $moduleRepository,
        private ModuleManagerServiceInterface $moduleManager,
        private GitHubReleaseFetcherInterface $githubFetcher,
    ) {}

    public function checkForUpdate(ModuleName $name): ?AvailableUpdateDTO
    {
        $module = $this->moduleRepository->findByName($name);

        if ($module === null) {
            return null;
        }

        // Check if module has a configured source
        $sourceOwner = $module->sourceOwner();
        $sourceRepo = $module->sourceRepo();

        if ($sourceOwner === null || $sourceRepo === null) {
            return null;
        }

        $latestRelease = $this->githubFetcher->getLatestRelease($sourceOwner, $sourceRepo);

        if ($latestRelease === null) {
            return null;
        }

        // Skip prereleases unless configured to allow them
        if ($latestRelease->isPrerelease && ! config('updates.behavior.allow_prereleases', false)) {
            return null;
        }

        // Check if update is available
        $currentVersion = $module->version();
        if (! $latestRelease->version->isGreaterThan($currentVersion)) {
            return null;
        }

        // Update the last check timestamp
        $module->updateLastCheckAt(new DateTimeImmutable());
        $module->updateLatestAvailableVersion($latestRelease->version->value());
        $this->moduleRepository->save($module);

        return new AvailableUpdateDTO(
            moduleName: $module->name()->value,
            displayName: $module->displayName(),
            currentVersion: $currentVersion->value(),
            availableVersion: $latestRelease->version->value(),
            releaseNotes: $latestRelease->releaseNotes,
            publishedAt: $latestRelease->publishedAt,
            isPrerelease: $latestRelease->isPrerelease,
            isMajorUpdate: $latestRelease->isMajorUpgradeFrom($currentVersion),
            downloadUrl: $latestRelease->downloadUrl,
            hasChecksum: $latestRelease->hasChecksum(),
        );
    }

    public function checkAllForUpdates(): Collection
    {
        $modules = $this->moduleRepository->all();
        $updates = new Collection();

        // Collect repos to check
        $reposToCheck = [];
        $modulesByRepo = [];

        foreach ($modules->all() as $module) {
            $sourceOwner = $module->sourceOwner();
            $sourceRepo = $module->sourceRepo();

            if ($sourceOwner === null || $sourceRepo === null) {
                continue;
            }

            $repoKey = "{$sourceOwner}/{$sourceRepo}";
            $reposToCheck[] = ['owner' => $sourceOwner, 'repo' => $sourceRepo];
            $modulesByRepo[$repoKey] = $module;
        }

        if (empty($reposToCheck)) {
            return $updates;
        }

        // Batch fetch all releases
        $releases = config('updates.batch_check', true)
            ? $this->githubFetcher->batchFetchLatestReleases($reposToCheck)
            : $this->fetchReleasesIndividually($reposToCheck);

        foreach ($releases as $repoKey => $release) {
            if ($release === null) {
                continue;
            }

            $module = $modulesByRepo[$repoKey] ?? null;
            if ($module === null) {
                continue;
            }

            // Skip prereleases unless configured
            if ($release->isPrerelease && ! config('updates.behavior.allow_prereleases', false)) {
                continue;
            }

            // Check if update is available
            $currentVersion = $module->version();
            if (! $release->version->isGreaterThan($currentVersion)) {
                continue;
            }

            // Update tracking info
            $module->updateLastCheckAt(new DateTimeImmutable());
            $module->updateLatestAvailableVersion($release->version->value());
            $this->moduleRepository->save($module);

            $updates->push(new AvailableUpdateDTO(
                moduleName: $module->name()->value,
                displayName: $module->displayName(),
                currentVersion: $currentVersion->value(),
                availableVersion: $release->version->value(),
                releaseNotes: $release->releaseNotes,
                publishedAt: $release->publishedAt,
                isPrerelease: $release->isPrerelease,
                isMajorUpdate: $release->isMajorUpgradeFrom($currentVersion),
                downloadUrl: $release->downloadUrl,
                hasChecksum: $release->hasChecksum(),
            ));
        }

        return $updates;
    }

    public function getLastCheckTime(ModuleName $name): ?DateTimeImmutable
    {
        $module = $this->moduleRepository->findByName($name);

        return $module?->lastUpdateCheckAt();
    }

    public function forceCheck(ModuleName $name): ?AvailableUpdateDTO
    {
        $module = $this->moduleRepository->findByName($name);

        if ($module === null) {
            return null;
        }

        $sourceOwner = $module->sourceOwner();
        $sourceRepo = $module->sourceRepo();

        if ($sourceOwner !== null && $sourceRepo !== null) {
            $this->githubFetcher->clearCache($sourceOwner, $sourceRepo);
        }

        return $this->checkForUpdate($name);
    }

    /**
     * @param  array<array{owner: string, repo: string}>  $repos
     * @return array<string, \App\Domain\Updates\ValueObjects\GitHubReleaseInfo|null>
     */
    private function fetchReleasesIndividually(array $repos): array
    {
        $results = [];

        foreach ($repos as $repo) {
            $key = "{$repo['owner']}/{$repo['repo']}";

            try {
                $results[$key] = $this->githubFetcher->getLatestRelease($repo['owner'], $repo['repo']);
            } catch (\Throwable $e) {
                Log::warning("Failed to fetch release for {$key}", ['error' => $e->getMessage()]);
                $results[$key] = null;
            }
        }

        return $results;
    }
}
