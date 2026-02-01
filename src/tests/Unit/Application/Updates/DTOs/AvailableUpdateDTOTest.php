<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Updates\DTOs;

use App\Application\Updates\DTOs\AvailableUpdateDTO;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AvailableUpdateDTOTest extends TestCase
{
    public function test_it_creates_dto_with_all_properties(): void
    {
        $publishedAt = new DateTimeImmutable('2024-08-15 10:30:00');

        $dto = new AvailableUpdateDTO(
            moduleName: 'forum',
            displayName: 'Forum Module',
            currentVersion: '1.0.0',
            availableVersion: '1.2.0',
            releaseNotes: '## What\'s New\n\n- Added threads feature\n- Performance improvements',
            publishedAt: $publishedAt,
            isPrerelease: false,
            isMajorUpdate: false,
            downloadUrl: 'https://github.com/owner/repo/releases/download/v1.2.0/forum.zip',
            hasChecksum: true,
        );

        $this->assertEquals('forum', $dto->moduleName);
        $this->assertEquals('Forum Module', $dto->displayName);
        $this->assertEquals('1.0.0', $dto->currentVersion);
        $this->assertEquals('1.2.0', $dto->availableVersion);
        $this->assertStringContainsString('threads feature', $dto->releaseNotes);
        $this->assertEquals($publishedAt, $dto->publishedAt);
        $this->assertFalse($dto->isPrerelease);
        $this->assertFalse($dto->isMajorUpdate);
        $this->assertStringContainsString('v1.2.0', $dto->downloadUrl);
        $this->assertTrue($dto->hasChecksum);
    }

    public function test_it_handles_prerelease_update(): void
    {
        $dto = new AvailableUpdateDTO(
            moduleName: 'shop',
            displayName: 'Shop Module',
            currentVersion: '2.0.0',
            availableVersion: '2.1.0-beta.1',
            releaseNotes: 'Beta release',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: true,
            isMajorUpdate: false,
            downloadUrl: 'https://example.com/download.zip',
            hasChecksum: false,
        );

        $this->assertTrue($dto->isPrerelease);
        $this->assertEquals('2.1.0-beta.1', $dto->availableVersion);
    }

    public function test_it_handles_major_update(): void
    {
        $dto = new AvailableUpdateDTO(
            moduleName: 'gallery',
            displayName: 'Gallery Module',
            currentVersion: '1.5.3',
            availableVersion: '2.0.0',
            releaseNotes: 'Major release with breaking changes',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
            isMajorUpdate: true,
            downloadUrl: 'https://example.com/download.zip',
            hasChecksum: true,
        );

        $this->assertTrue($dto->isMajorUpdate);
    }

    public function test_to_array_returns_correct_representation(): void
    {
        $publishedAt = new DateTimeImmutable('2024-12-01T14:30:00Z');

        $dto = new AvailableUpdateDTO(
            moduleName: 'events',
            displayName: 'Events Module',
            currentVersion: '3.0.0',
            availableVersion: '3.1.5',
            releaseNotes: 'Bug fixes',
            publishedAt: $publishedAt,
            isPrerelease: false,
            isMajorUpdate: false,
            downloadUrl: 'https://github.com/test/events/download.zip',
            hasChecksum: true,
        );

        $array = $dto->toArray();

        $this->assertEquals('events', $array['module_name']);
        $this->assertEquals('Events Module', $array['display_name']);
        $this->assertEquals('3.0.0', $array['current_version']);
        $this->assertEquals('3.1.5', $array['available_version']);
        $this->assertEquals('Bug fixes', $array['release_notes']);
        $this->assertEquals($publishedAt->format('c'), $array['published_at']);
        $this->assertFalse($array['is_prerelease']);
        $this->assertFalse($array['is_major_update']);
        $this->assertEquals('https://github.com/test/events/download.zip', $array['download_url']);
        $this->assertTrue($array['has_checksum']);
    }

    public function test_to_array_formats_published_at_in_iso_format(): void
    {
        $publishedAt = new DateTimeImmutable('2024-06-15T08:45:30+02:00');

        $dto = new AvailableUpdateDTO(
            moduleName: 'test',
            displayName: 'Test Module',
            currentVersion: '1.0.0',
            availableVersion: '1.1.0',
            releaseNotes: '',
            publishedAt: $publishedAt,
            isPrerelease: false,
            isMajorUpdate: false,
            downloadUrl: '',
            hasChecksum: false,
        );

        $array = $dto->toArray();

        // The 'c' format is ISO 8601
        $this->assertStringContainsString('2024-06-15', $array['published_at']);
    }

    public function test_dto_is_readonly(): void
    {
        $dto = new AvailableUpdateDTO(
            moduleName: 'test',
            displayName: 'Test',
            currentVersion: '1.0.0',
            availableVersion: '1.1.0',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
            isMajorUpdate: false,
            downloadUrl: '',
            hasChecksum: false,
        );

        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function test_it_handles_update_without_checksum(): void
    {
        $dto = new AvailableUpdateDTO(
            moduleName: 'legacy',
            displayName: 'Legacy Module',
            currentVersion: '0.9.0',
            availableVersion: '1.0.0',
            releaseNotes: 'Initial stable release',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
            isMajorUpdate: true,
            downloadUrl: 'https://example.com/legacy.zip',
            hasChecksum: false,
        );

        $this->assertFalse($dto->hasChecksum);

        $array = $dto->toArray();
        $this->assertFalse($array['has_checksum']);
    }

    public function test_it_handles_empty_release_notes(): void
    {
        $dto = new AvailableUpdateDTO(
            moduleName: 'minimal',
            displayName: 'Minimal Module',
            currentVersion: '1.0.0',
            availableVersion: '1.0.1',
            releaseNotes: '',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: false,
            isMajorUpdate: false,
            downloadUrl: 'https://example.com/minimal.zip',
            hasChecksum: true,
        );

        $this->assertEquals('', $dto->releaseNotes);

        $array = $dto->toArray();
        $this->assertEquals('', $array['release_notes']);
    }
}
