<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Domain\Modules\Exceptions\InvalidModuleVersionException;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubCommitComparison;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GitHubReleaseFetcher implements GitHubReleaseFetcherInterface
{
    private const string CACHE_KEY_PREFIX = 'github_releases';

    /** @var list<string> Cache keys written by this instance, so clearCache() can forget only them */
    private array $cachedKeys = [];

    public function getLatestRelease(string $owner, string $repo, bool $includePrereleases = false): ?GitHubReleaseInfo
    {
        $latest = null;

        foreach ($this->fetchReleases($owner, $repo) as $data) {
            if (($data['draft'] ?? false) === true) {
                continue;
            }

            if (($data['prerelease'] ?? false) === true && ! $includePrereleases) {
                continue;
            }

            try {
                $release = GitHubReleaseInfo::fromGitHubResponse($data);
            } catch (InvalidModuleVersionException) {
                continue; // Tags such as "nightly" are not versions
            }

            if ($latest === null || $release->version->isGreaterThan($latest->version)) {
                $latest = $release;
            }
        }

        return $latest;
    }

    public function downloadRelease(GitHubReleaseInfo $release, string $destinationPath): string
    {
        if (! $release->hasDownloadableAssets()) {
            throw UpdateException::noDownloadableAssets($release->tagName);
        }

        $this->ensureDirectoryExists(dirname($destinationPath));

        $response = $this->createClient()
            ->timeout(300) // 5 minutes for large downloads
            ->withOptions(['sink' => $destinationPath])
            ->get($release->downloadUrl);

        if (! $response->successful()) {
            throw UpdateException::downloadFailed(
                $release->tagName,
                "HTTP {$response->status()}: {$response->body()}"
            );
        }

        return $destinationPath;
    }

    public function fetchAndVerifyChecksum(GitHubReleaseInfo $release, string $downloadedFilePath): bool
    {
        if (! $release->hasChecksum()) {
            Log::warning("No checksum available for release {$release->tagName}");

            return true; // Skip verification if no checksum
        }

        try {
            $response = $this->createClient()->get($release->checksumUrl);

            if (! $response->successful()) {
                throw UpdateException::checksumFetchFailed($release->tagName);
            }

            // Parse checksum file (format: "sha256hash  filename")
            $checksumContent = trim($response->body());
            $parts = preg_split('/\s+/', $checksumContent);
            $expectedChecksum = $parts[0] ?? '';

            if (empty($expectedChecksum)) {
                throw UpdateException::checksumFetchFailed($release->tagName);
            }

            // Calculate actual checksum
            $actualChecksum = hash_file('sha256', $downloadedFilePath);

            if ($actualChecksum !== $expectedChecksum) {
                Log::error("Checksum mismatch for {$release->tagName}", [
                    'expected' => $expectedChecksum,
                    'actual' => $actualChecksum,
                ]);

                throw UpdateException::checksumMismatch($release->tagName);
            }

            return true;
        } catch (UpdateException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("Checksum verification error for {$release->tagName}", [
                'error' => $e->getMessage(),
            ]);

            throw UpdateException::checksumFetchFailed($release->tagName);
        }
    }

    public function batchFetchLatestReleases(array $repos, bool $includePrereleases = false): array
    {
        $results = [];

        foreach ($repos as $repo) {
            $key = "{$repo['owner']}/{$repo['repo']}";

            try {
                $results[$key] = $this->getLatestRelease($repo['owner'], $repo['repo'], $includePrereleases);
            } catch (UpdateException $e) {
                $results[$key] = $e;
            }
        }

        return $results;
    }

    public function clearCache(?string $owner = null, ?string $repo = null): void
    {
        if ($owner !== null && $repo !== null) {
            Cache::forget($this->getCacheKey($owner, $repo));

            return;
        }

        foreach ($this->cachedKeys as $key) {
            Cache::forget($key);
        }

        $this->cachedKeys = [];
    }

    public function compareCommits(string $owner, string $repo, string $base, string $head): GitHubCommitComparison
    {
        try {
            $response = $this->createClient()->get("repos/{$owner}/{$repo}/compare/{$base}...{$head}");
        } catch (\Throwable $e) {
            throw UpdateException::githubRequestFailed("{$owner}/{$repo}", $e->getMessage());
        }

        if (! $response->successful()) {
            throw UpdateException::githubRequestFailed("{$owner}/{$repo}", "HTTP {$response->status()}");
        }

        $commits = [];

        foreach ((array) $response->json('commits', []) as $commit) {
            if (! is_array($commit)) {
                continue;
            }

            $message = (string) data_get($commit, 'commit.message', '');
            $date = data_get($commit, 'commit.author.date');

            $commits[] = [
                'sha' => (string) ($commit['sha'] ?? ''),
                // Subject line only
                'message' => trim(strtok($message, "\n") ?: ''),
                'date' => is_string($date) ? $date : null,
                'url' => (string) ($commit['html_url'] ?? ''),
            ];
        }

        // GitHub lists them oldest first
        $commits = array_reverse($commits);

        return new GitHubCommitComparison(
            aheadBy: (int) $response->json('ahead_by', 0),
            behindBy: (int) $response->json('behind_by', 0),
            headSha: $commits[0]['sha'] ?? $base,
            commits: $commits,
        );
    }

    /**
     * Raw release list of a repository, cached only when GitHub answered successfully.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchReleases(string $owner, string $repo): array
    {
        $cacheKey = $this->getCacheKey($owner, $repo);

        /** @var list<array<string, mixed>>|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->createClient()->get("repos/{$owner}/{$repo}/releases", ['per_page' => 30]);
        } catch (\Throwable $e) {
            throw UpdateException::githubRequestFailed("{$owner}/{$repo}", $e->getMessage());
        }

        if (! $response->successful()) {
            Log::warning("GitHub API error for {$owner}/{$repo}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw UpdateException::githubRequestFailed("{$owner}/{$repo}", "HTTP {$response->status()}");
        }

        $releases = $response->json();
        $releases = is_array($releases) ? array_values(array_filter($releases, 'is_array')) : [];

        Cache::put($cacheKey, $releases, (int) config('updates.cache.ttl', 3600));
        $this->cachedKeys[] = $cacheKey;

        return $releases;
    }

    private function createClient(): PendingRequest
    {
        $baseUrl = config('updates.github.api_base_url', 'https://api.github.com');
        $timeout = config('updates.github.timeout', 30);
        $token = config('updates.github.token');

        $client = Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->accept('application/vnd.github.v3+json')
            ->withUserAgent('GuildForge-Updater/1.0');

        if ($token !== null && $token !== '') {
            $client->withToken($token);
        }

        return $client;
    }

    private function getCacheKey(string $owner, string $repo): string
    {
        $prefix = config('updates.cache.key_prefix', 'updates');

        return "{$prefix}.".self::CACHE_KEY_PREFIX.".{$owner}.{$repo}";
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
