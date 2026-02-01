<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class GitHubReleaseFetcher implements GitHubReleaseFetcherInterface
{
    private const string CACHE_KEY_PREFIX = 'github_release';

    public function getLatestRelease(string $owner, string $repo): ?GitHubReleaseInfo
    {
        $cacheKey = $this->getCacheKey($owner, $repo);
        $cacheTtl = config('updates.cache.ttl', 3600);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($owner, $repo) {
            return $this->fetchLatestRelease($owner, $repo);
        });
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

    public function batchFetchLatestReleases(array $repos): array
    {
        $results = [];
        $uncached = [];

        // First, check cache for all repos
        foreach ($repos as $repo) {
            $key = "{$repo['owner']}/{$repo['repo']}";
            $cacheKey = $this->getCacheKey($repo['owner'], $repo['repo']);

            if (Cache::has($cacheKey)) {
                $results[$key] = Cache::get($cacheKey);
            } else {
                $uncached[] = $repo;
            }
        }

        // Fetch uncached repos
        foreach ($uncached as $repo) {
            $key = "{$repo['owner']}/{$repo['repo']}";

            try {
                $release = $this->fetchLatestRelease($repo['owner'], $repo['repo']);
                $results[$key] = $release;

                // Cache the result
                $cacheKey = $this->getCacheKey($repo['owner'], $repo['repo']);
                Cache::put($cacheKey, $release, config('updates.cache.ttl', 3600));
            } catch (\Throwable $e) {
                Log::warning("Failed to fetch release for {$key}", [
                    'error' => $e->getMessage(),
                ]);
                $results[$key] = null;
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

        // Clear all release cache (using pattern if cache driver supports it)
        $prefix = config('updates.cache.key_prefix', 'updates');
        Cache::flush(); // Simplified - in production, use tagged caches
    }

    private function fetchLatestRelease(string $owner, string $repo): ?GitHubReleaseInfo
    {
        try {
            $response = $this->createClient()
                ->get("repos/{$owner}/{$repo}/releases/latest");

            if ($response->status() === 404) {
                return null;
            }

            if (! $response->successful()) {
                Log::warning("GitHub API error for {$owner}/{$repo}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            if (empty($data)) {
                return null;
            }

            return GitHubReleaseInfo::fromGitHubResponse($data);
        } catch (\Throwable $e) {
            Log::error("Failed to fetch latest release for {$owner}/{$repo}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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

        return "{$prefix}." . self::CACHE_KEY_PREFIX . ".{$owner}.{$repo}";
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
