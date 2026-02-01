<?php

declare(strict_types=1);

namespace App\Domain\Updates\ValueObjects;

use App\Domain\Modules\ValueObjects\ModuleVersion;
use DateTimeImmutable;

/**
 * Represents release information fetched from GitHub Releases API.
 */
final readonly class GitHubReleaseInfo
{
    public function __construct(
        public string $tagName,
        public ModuleVersion $version,
        public string $downloadUrl,
        public string $checksumUrl,
        public string $releaseNotes,
        public DateTimeImmutable $publishedAt,
        public bool $isPrerelease,
    ) {}

    /**
     * Create from GitHub API response.
     *
     * @param  array<string, mixed>  $data  Raw GitHub API response
     */
    public static function fromGitHubResponse(array $data): self
    {
        $tagName = $data['tag_name'] ?? '';
        $versionString = ltrim($tagName, 'v');

        // Find the ZIP asset
        $downloadUrl = '';
        $checksumUrl = '';

        foreach ($data['assets'] ?? [] as $asset) {
            $name = $asset['name'] ?? '';

            if (str_ends_with($name, '.zip') && ! str_ends_with($name, '.sha256')) {
                $downloadUrl = $asset['browser_download_url'] ?? '';
            }

            if (str_ends_with($name, '.sha256')) {
                $checksumUrl = $asset['browser_download_url'] ?? '';
            }
        }

        return new self(
            tagName: $tagName,
            version: ModuleVersion::fromString($versionString),
            downloadUrl: $downloadUrl,
            checksumUrl: $checksumUrl,
            releaseNotes: $data['body'] ?? '',
            publishedAt: new DateTimeImmutable($data['published_at'] ?? 'now'),
            isPrerelease: $data['prerelease'] ?? false,
        );
    }

    /**
     * Check if this is a valid release with downloadable assets.
     */
    public function hasDownloadableAssets(): bool
    {
        return $this->downloadUrl !== '';
    }

    /**
     * Check if checksum verification is available.
     */
    public function hasChecksum(): bool
    {
        return $this->checksumUrl !== '';
    }

    /**
     * Check if this is a major version upgrade compared to given version.
     */
    public function isMajorUpgradeFrom(ModuleVersion $currentVersion): bool
    {
        return $this->version->major > $currentVersion->major;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tag_name' => $this->tagName,
            'version' => $this->version->value(),
            'download_url' => $this->downloadUrl,
            'checksum_url' => $this->checksumUrl,
            'release_notes' => $this->releaseNotes,
            'published_at' => $this->publishedAt->format('c'),
            'is_prerelease' => $this->isPrerelease,
        ];
    }
}
