<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubCommitComparison;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use App\Domain\Updates\ValueObjects\ReleaseSelection;

/**
 * Service for fetching release information from GitHub.
 */
interface GitHubReleaseFetcherInterface
{
    /**
     * Choose among the published releases newer than $installed (drafts skipped; prereleases only
     * when $includePrereleases): the highest one whose module.json this host satisfies, and the
     * highest incompatible one newer than it. Releases without a module.json asset are evaluated
     * with the default core constraint.
     *
     * @throws UpdateException When GitHub cannot be queried or a release's module.json cannot be downloaded
     */
    public function selectRelease(string $owner, string $repo, ModuleVersion $installed, bool $includePrereleases): ReleaseSelection;

    /**
     * Download a release ZIP file to local storage.
     *
     * @return string Path to the downloaded file
     */
    public function downloadRelease(GitHubReleaseInfo $release, string $destinationPath): string;

    /**
     * Fetch checksum from GitHub and verify the downloaded file.
     */
    public function fetchAndVerifyChecksum(GitHubReleaseInfo $release, string $downloadedFilePath): bool;

    /**
     * A repository that cannot be queried maps to its UpdateException, so one failure
     * does not hide the results of the others.
     *
     * @param  list<array{owner: string, repo: string, installed: ModuleVersion}>  $repos
     * @return array<string, ReleaseSelection|UpdateException> Keyed by "owner/repo"
     */
    public function batchSelectReleases(array $repos, bool $includePrereleases = false): array;

    /**
     * Clear cached release information.
     */
    public function clearCache(?string $owner = null, ?string $repo = null): void;

    /**
     * Compare a commit with a branch (or any other ref) of a repository.
     *
     * @throws UpdateException When GitHub cannot be reached or answers with an error
     */
    public function compareCommits(string $owner, string $repo, string $base, string $head): GitHubCommitComparison;
}
