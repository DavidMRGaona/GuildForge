<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Application\Modules\Services\HostEnvironmentProviderInterface;
use App\Application\Modules\Services\ModuleCompatibilityChecker;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\Exceptions\InvalidModuleVersionException;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\CompatibilityResult;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Domain\Updates\ValueObjects\GitHubCommitComparison;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use App\Domain\Updates\ValueObjects\ReleaseSelection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GitHubReleaseFetcher implements GitHubReleaseFetcherInterface
{
    private const string CACHE_KEY_PREFIX = 'github_releases';

    /** A module.json is a few hundred bytes; anything far larger is not a manifest */
    private const int MANIFEST_MAX_BYTES = 65536;

    /** @var list<string> Cache keys written by this instance, so clearCache() can forget only them */
    private array $cachedKeys = [];

    public function __construct(
        private readonly ModuleCompatibilityChecker $checker,
        private readonly HostEnvironmentProviderInterface $hostEnvironment,
    ) {
    }

    public function selectRelease(string $owner, string $repo, ModuleVersion $installed, bool $includePrereleases): ReleaseSelection
    {
        $host = $this->hostEnvironment->current();
        $blocked = null;
        $blockedConstraint = null;
        $blockedIssues = [];

        foreach ($this->candidates($owner, $repo, $installed, $includePrereleases) as $release) {
            $requirements = $this->releaseRequirements($owner, $repo, $release);

            if ($requirements === null) {
                $result = new CompatibilityResult([new CompatibilityIssue(RequirementType::Manifest, '', null, CompatibilityIssue::MANIFEST_INVALID)]);
                $constraint = null;
            } else {
                $result = $this->checker->check($requirements, $host);
                $constraint = $requirements->effectiveCoreConstraint();
            }

            if ($result->isCompatible()) {
                return new ReleaseSelection($release, $constraint, $blocked, $blockedConstraint, $blockedIssues);
            }

            // Candidates come newest first: the first incompatible one is the highest
            if ($blocked === null) {
                $blocked = $release;
                $blockedConstraint = $constraint;
                $blockedIssues = $result->issues;
            }
        }

        return new ReleaseSelection(null, null, $blocked, $blockedConstraint, $blockedIssues);
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

    public function batchSelectReleases(array $repos, bool $includePrereleases = false): array
    {
        $results = [];

        foreach ($repos as $repo) {
            $key = "{$repo['owner']}/{$repo['repo']}";

            try {
                $results[$key] = $this->selectRelease($repo['owner'], $repo['repo'], $repo['installed'], $includePrereleases);
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
            Cache::forget($this->getManifestsCacheKey($owner, $repo));

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

    /**
     * Downloadable releases newer than the installed version, newest first.
     *
     * @return list<GitHubReleaseInfo>
     */
    private function candidates(string $owner, string $repo, ModuleVersion $installed, bool $includePrereleases): array
    {
        $candidates = [];

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

            if ($release->hasDownloadableAssets() && $release->version->isGreaterThan($installed)) {
                $candidates[] = $release;
            }
        }

        usort($candidates, static fn (GitHubReleaseInfo $a, GitHubReleaseInfo $b): int => match (true) {
            $a->version->isGreaterThan($b->version) => -1,
            $b->version->isGreaterThan($a->version) => 1,
            default => 0,
        });

        return $candidates;
    }

    /**
     * Requirements published with a release, or null when its module.json asset is not a JSON object.
     * Releases published before the asset existed declare nothing, so the default core constraint applies.
     */
    private function releaseRequirements(string $owner, string $repo, GitHubReleaseInfo $release): ?ModuleRequirements
    {
        if (! $release->hasManifest()) {
            return ModuleRequirements::fromManifest([]);
        }

        $cacheKey = $this->getManifestsCacheKey($owner, $repo);
        /** @var array<string, array{requires?: array<string, mixed>, invalid?: bool}> $manifests */
        $manifests = Cache::get($cacheKey, []);

        if (! isset($manifests[$release->tagName])) {
            $manifests[$release->tagName] = $this->downloadManifest($owner, $repo, $release);
            Cache::put($cacheKey, $manifests, (int) config('updates.cache.ttl', 3600));
            $this->cachedKeys[] = $cacheKey;
        }

        $requires = $manifests[$release->tagName]['requires'] ?? null;

        return $requires === null ? null : ModuleRequirements::fromManifest($requires);
    }

    /**
     * @return array{requires?: array<string, mixed>, invalid?: bool}
     */
    private function downloadManifest(string $owner, string $repo, GitHubReleaseInfo $release): array
    {
        try {
            $response = $this->createClient()->get($release->manifestUrl);
        } catch (\Throwable $e) {
            throw UpdateException::githubRequestFailed("{$owner}/{$repo}", "module.json of {$release->tagName}: {$e->getMessage()}");
        }

        // Never "up to date" because a download failed: the check reports it as an error
        if (! $response->successful()) {
            throw UpdateException::githubRequestFailed("{$owner}/{$repo}", "module.json of {$release->tagName}: HTTP {$response->status()}");
        }

        $body = $response->body();

        if (strlen($body) > self::MANIFEST_MAX_BYTES) {
            return ['invalid' => true];
        }

        $data = json_decode($body, true);

        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            return ['invalid' => true];
        }

        return ['requires' => ModuleManifestDTO::normalizeRequires($data['requires'] ?? null)['requires']];
    }

    private function getManifestsCacheKey(string $owner, string $repo): string
    {
        return $this->getCacheKey($owner, $repo).'.manifests';
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
