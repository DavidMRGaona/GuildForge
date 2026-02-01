<?php

declare(strict_types=1);

namespace App\Application\Updates\Services;

use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;

/**
 * Service for fetching release information from GitHub.
 */
interface GitHubReleaseFetcherInterface
{
    /**
     * Get the latest release for a repository.
     */
    public function getLatestRelease(string $owner, string $repo): ?GitHubReleaseInfo;

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
     * @param  array<array{owner: string, repo: string}>  $repos
     * @return array<string, GitHubReleaseInfo|null> Keyed by "owner/repo"
     */
    public function batchFetchLatestReleases(array $repos): array;

    /**
     * Clear cached release information.
     */
    public function clearCache(?string $owner = null, ?string $repo = null): void;
}
