<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Updates\ValueObjects;

use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GitHubReleaseInfoTest extends TestCase
{
    public function test_it_creates_from_constructor_with_all_properties(): void
    {
        $version = ModuleVersion::fromString('1.2.3');
        $publishedAt = new DateTimeImmutable('2024-06-15 10:30:00');

        $release = new GitHubReleaseInfo(
            tagName: 'v1.2.3',
            version: $version,
            downloadUrl: 'https://github.com/owner/repo/releases/download/v1.2.3/module.zip',
            checksumUrl: 'https://github.com/owner/repo/releases/download/v1.2.3/module.zip.sha256',
            releaseNotes: 'Bug fixes and improvements',
            publishedAt: $publishedAt,
            isPrerelease: false,
        );

        $this->assertEquals('v1.2.3', $release->tagName);
        $this->assertEquals($version, $release->version);
        $this->assertEquals('https://github.com/owner/repo/releases/download/v1.2.3/module.zip', $release->downloadUrl);
        $this->assertEquals('https://github.com/owner/repo/releases/download/v1.2.3/module.zip.sha256', $release->checksumUrl);
        $this->assertEquals('Bug fixes and improvements', $release->releaseNotes);
        $this->assertEquals($publishedAt, $release->publishedAt);
        $this->assertFalse($release->isPrerelease);
    }

    public function test_it_creates_from_github_response_array(): void
    {
        $data = [
            'tag_name' => 'v2.0.0',
            'body' => 'Major release with breaking changes',
            'published_at' => '2024-07-20T14:30:00Z',
            'prerelease' => false,
            'assets' => [
                [
                    'name' => 'module-2.0.0.zip',
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/v2.0.0/module-2.0.0.zip',
                ],
                [
                    'name' => 'module-2.0.0.zip.sha256',
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/v2.0.0/module-2.0.0.zip.sha256',
                ],
            ],
        ];

        $release = GitHubReleaseInfo::fromGitHubResponse($data);

        $this->assertEquals('v2.0.0', $release->tagName);
        $this->assertEquals('2.0.0', $release->version->value());
        $this->assertEquals('https://github.com/owner/repo/releases/download/v2.0.0/module-2.0.0.zip', $release->downloadUrl);
        $this->assertEquals('https://github.com/owner/repo/releases/download/v2.0.0/module-2.0.0.zip.sha256', $release->checksumUrl);
        $this->assertEquals('Major release with breaking changes', $release->releaseNotes);
        $this->assertFalse($release->isPrerelease);
    }

    public function test_it_extracts_version_from_tag_name_with_v_prefix(): void
    {
        $data = [
            'tag_name' => 'v1.5.10',
            'body' => '',
            'published_at' => '2024-01-01T00:00:00Z',
            'prerelease' => false,
            'assets' => [],
        ];

        $release = GitHubReleaseInfo::fromGitHubResponse($data);

        $this->assertEquals('1.5.10', $release->version->value());
    }

    public function test_it_extracts_version_from_tag_name_without_prefix(): void
    {
        $data = [
            'tag_name' => '3.2.1',
            'body' => '',
            'published_at' => '2024-01-01T00:00:00Z',
            'prerelease' => false,
            'assets' => [],
        ];

        $release = GitHubReleaseInfo::fromGitHubResponse($data);

        $this->assertEquals('3.2.1', $release->version->value());
    }

    public function test_it_parses_published_at_correctly(): void
    {
        $data = [
            'tag_name' => 'v1.0.0',
            'body' => '',
            'published_at' => '2024-12-25T18:45:30Z',
            'prerelease' => false,
            'assets' => [],
        ];

        $release = GitHubReleaseInfo::fromGitHubResponse($data);

        $this->assertEquals('2024-12-25', $release->publishedAt->format('Y-m-d'));
        $this->assertEquals('18:45:30', $release->publishedAt->format('H:i:s'));
    }

    public function test_it_identifies_prerelease(): void
    {
        // Prerelease flag comes from GitHub API response, not from tag name parsing
        // The version parsing strips non-semver suffixes, so we use a clean version
        $release = new GitHubReleaseInfo(
            tagName: 'v2.0.0-beta.1',
            version: ModuleVersion::fromString('2.0.0'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: 'Beta release',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: true,
        );

        $this->assertTrue($release->isPrerelease);
        $this->assertEquals('v2.0.0-beta.1', $release->tagName);
    }

    public function test_has_downloadable_assets_returns_true_when_download_url_exists(): void
    {
        $release = new GitHubReleaseInfo(
            tagName: 'v1.0.0',
            version: ModuleVersion::fromString('1.0.0'),
            downloadUrl: 'https://example.com/download.zip',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $this->assertTrue($release->hasDownloadableAssets());
    }

    public function test_has_downloadable_assets_returns_false_when_download_url_is_empty(): void
    {
        $release = new GitHubReleaseInfo(
            tagName: 'v1.0.0',
            version: ModuleVersion::fromString('1.0.0'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $this->assertFalse($release->hasDownloadableAssets());
    }

    public function test_has_checksum_returns_true_when_checksum_url_exists(): void
    {
        $release = new GitHubReleaseInfo(
            tagName: 'v1.0.0',
            version: ModuleVersion::fromString('1.0.0'),
            downloadUrl: 'https://example.com/download.zip',
            checksumUrl: 'https://example.com/download.zip.sha256',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $this->assertTrue($release->hasChecksum());
    }

    public function test_has_checksum_returns_false_when_checksum_url_is_empty(): void
    {
        $release = new GitHubReleaseInfo(
            tagName: 'v1.0.0',
            version: ModuleVersion::fromString('1.0.0'),
            downloadUrl: 'https://example.com/download.zip',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $this->assertFalse($release->hasChecksum());
    }

    public function test_is_major_upgrade_from_returns_true_for_major_version_change(): void
    {
        $release = new GitHubReleaseInfo(
            tagName: 'v2.0.0',
            version: ModuleVersion::fromString('2.0.0'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $currentVersion = ModuleVersion::fromString('1.5.3');

        $this->assertTrue($release->isMajorUpgradeFrom($currentVersion));
    }

    public function test_is_major_upgrade_from_returns_false_for_minor_version_change(): void
    {
        $release = new GitHubReleaseInfo(
            tagName: 'v1.6.0',
            version: ModuleVersion::fromString('1.6.0'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $currentVersion = ModuleVersion::fromString('1.5.3');

        $this->assertFalse($release->isMajorUpgradeFrom($currentVersion));
    }

    public function test_is_major_upgrade_from_returns_false_for_patch_version_change(): void
    {
        $release = new GitHubReleaseInfo(
            tagName: 'v1.5.4',
            version: ModuleVersion::fromString('1.5.4'),
            downloadUrl: '',
            checksumUrl: '',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
        );

        $currentVersion = ModuleVersion::fromString('1.5.3');

        $this->assertFalse($release->isMajorUpgradeFrom($currentVersion));
    }

    public function test_to_array_returns_correct_array_representation(): void
    {
        $publishedAt = new DateTimeImmutable('2024-08-15T12:00:00Z');

        $release = new GitHubReleaseInfo(
            tagName: 'v1.2.3',
            version: ModuleVersion::fromString('1.2.3'),
            downloadUrl: 'https://example.com/download.zip',
            checksumUrl: 'https://example.com/download.zip.sha256',
            releaseNotes: 'Release notes here',
            publishedAt: $publishedAt,
            isPrerelease: false,
        );

        $array = $release->toArray();

        $this->assertEquals('v1.2.3', $array['tag_name']);
        $this->assertEquals('1.2.3', $array['version']);
        $this->assertEquals('https://example.com/download.zip', $array['download_url']);
        $this->assertEquals('https://example.com/download.zip.sha256', $array['checksum_url']);
        $this->assertEquals('Release notes here', $array['release_notes']);
        $this->assertEquals($publishedAt->format('c'), $array['published_at']);
        $this->assertFalse($array['is_prerelease']);
    }

    public function test_it_handles_missing_optional_fields_in_github_response(): void
    {
        $data = [
            'tag_name' => 'v1.0.0',
        ];

        $release = GitHubReleaseInfo::fromGitHubResponse($data);

        $this->assertEquals('v1.0.0', $release->tagName);
        $this->assertEquals('', $release->downloadUrl);
        $this->assertEquals('', $release->checksumUrl);
        $this->assertEquals('', $release->releaseNotes);
        $this->assertFalse($release->isPrerelease);
    }

    public function test_it_handles_assets_without_zip_file(): void
    {
        $data = [
            'tag_name' => 'v1.0.0',
            'body' => '',
            'published_at' => '2024-01-01T00:00:00Z',
            'prerelease' => false,
            'assets' => [
                [
                    'name' => 'changelog.md',
                    'browser_download_url' => 'https://example.com/changelog.md',
                ],
                [
                    'name' => 'readme.txt',
                    'browser_download_url' => 'https://example.com/readme.txt',
                ],
            ],
        ];

        $release = GitHubReleaseInfo::fromGitHubResponse($data);

        $this->assertEquals('', $release->downloadUrl);
        $this->assertFalse($release->hasDownloadableAssets());
    }
}
