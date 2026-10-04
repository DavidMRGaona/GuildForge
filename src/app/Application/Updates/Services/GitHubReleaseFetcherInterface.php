<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubCommitComparison;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;

/**
 * Service for fetching release information from GitHub.
 */
interface GitHubReleaseFetcherInterface
{
    /**
     * Get the highest-versioned published release of a repository.
     *
     * Drafts are ignored, and prereleases too unless $includePrereleases is true.
     * Returns null when the repository has no matching release.
     *
     * @throws UpdateException When GitHub cannot be queried (missing or private repo, rate limit, network)
     */
    public function getLatestRelease(string $owner, string $repo, bool $includePrereleases = false): ?GitHubReleaseInfo;

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
     * Batch fetch latest releases for multiple repositories.
     *
     * A repository that cannot be queried maps to its UpdateException, so one
     * failure does not hide the results of the others.
     *
     * @param  array<array{owner: string, repo: string}>  $repos
     * @return array<string, GitHubReleaseInfo|UpdateException|null> Keyed by "owner/repo"
     */
    public function batchFetchLatestReleases(array $repos, bool $includePrereleases = false): array;

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
