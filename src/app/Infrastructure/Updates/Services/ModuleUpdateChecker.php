<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Updates\DTOs\AvailableUpdateDTO;
use App\Application\Updates\DTOs\UpdateCheckResultDTO;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Application\Updates\Services\ReleaseChannelPolicy;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final readonly class ModuleUpdateChecker implements ModuleUpdateCheckerInterface
{
    public function __construct(
        private ModuleRepositoryInterface $moduleRepository,
        private GitHubReleaseFetcherInterface $githubFetcher,
        private ReleaseChannelPolicy $channelPolicy,
    ) {}

    public function checkForUpdate(ModuleName $name): ?AvailableUpdateDTO
    {
        $module = $this->moduleRepository->findByName($name);

        if ($module === null) {
            return null;
        }

        $sourceOwner = $module->sourceOwner();
        $sourceRepo = $module->sourceRepo();

        if ($sourceOwner === null || $sourceRepo === null) {
            return null;
        }

        $latestRelease = $this->githubFetcher->getLatestRelease(
            $sourceOwner,
            $sourceRepo,
            $this->channelPolicy->includesPrereleasesFor($module->version()),
        );

        return $this->recordCheck($module, $latestRelease);
    }

    public function checkAllForUpdates(): Collection
    {
        return $this->checkAll()->updates;
    }

    public function checkAll(bool $fresh = false): UpdateCheckResultDTO
    {
        /** @var Collection<int, AvailableUpdateDTO> $updates */
        $updates = new Collection;
        $errors = [];
        $modulesWithoutSource = [];

        // Modules on a prerelease follow a different channel, so fetch each channel in its own batch
        $reposByChannel = [false => [], true => []];
        $modulesByRepo = [];

        foreach ($this->moduleRepository->all()->all() as $module) {
            $sourceOwner = $module->sourceOwner();
            $sourceRepo = $module->sourceRepo();

            if ($sourceOwner === null || $sourceRepo === null) {
                $modulesWithoutSource[] = $module->name()->value;

                continue;
            }

            if ($fresh) {
                $this->githubFetcher->clearCache($sourceOwner, $sourceRepo);
            }

            $includePrereleases = $this->channelPolicy->includesPrereleasesFor($module->version());
            $reposByChannel[$includePrereleases][] = ['owner' => $sourceOwner, 'repo' => $sourceRepo];
            $modulesByRepo["{$sourceOwner}/{$sourceRepo}"] = $module;
        }

        foreach ($reposByChannel as $includePrereleases => $repos) {
            if ($repos === []) {
                continue;
            }

            $releases = $this->githubFetcher->batchFetchLatestReleases($repos, (bool) $includePrereleases);

            foreach ($releases as $repoKey => $release) {
                $module = $modulesByRepo[$repoKey] ?? null;

                if ($module === null) {
                    continue;
                }

                if ($release instanceof UpdateException) {
                    $errors[$module->name()->value] = $release->getMessage();

                    continue;
                }

                $update = $this->recordCheck($module, $release);

                if ($update !== null) {
                    $updates->push($update);
                }
            }
        }

        return new UpdateCheckResultDTO($updates, $errors, $modulesWithoutSource);
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
     * Persist the outcome of a check so the admin page can show pending updates
     * without querying GitHub again.
     */
    private function recordCheck(Module $module, ?GitHubReleaseInfo $release): ?AvailableUpdateDTO
    {
        $currentVersion = $module->version();
        $isUpdate = $release !== null
            && ($this->channelPolicy->includesPrereleasesFor($currentVersion) || ! $release->isPrerelease)
            && $release->version->isGreaterThan($currentVersion);

        $module->updateLastCheckAt(new DateTimeImmutable);

        if (! $isUpdate) {
            $module->clearLatestAvailableVersion();
            $this->moduleRepository->save($module);

            return null;
        }

        $module->updateLatestAvailableVersion($release->version->value());
        $this->moduleRepository->save($module);

        return new AvailableUpdateDTO(
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
        );
    }
}
