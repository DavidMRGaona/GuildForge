<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Storage\Cloudinary;

use App\Infrastructure\Storage\Cloudinary\CloudinaryPathMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CloudinaryPathMapperTest extends TestCase
{
    #[DataProvider('resources')]
    public function test_it_maps_paths_to_public_ids_and_resource_types(string $path, string $publicId, string $type): void
    {
        $this->assertSame([$publicId, $type], (new CloudinaryPathMapper('guildforge'))->toResource($path));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function resources(): array
    {
        return [
            'image in a dated folder' => ['events/2026/01/3f2a.jpg', 'guildforge/events/2026/01/3f2a', 'image'],
            'webp image' => ['hero/slide.webp', 'guildforge/hero/slide', 'image'],
            'path that already carries the prefix' => ['guildforge/events/3f2a.jpg', 'guildforge/events/3f2a', 'image'],
            'folder that only starts like the prefix' => ['guildforge-old/a.jpg', 'guildforge/guildforge-old/a', 'image'],
            'file at the root' => ['logo.png', 'guildforge/logo', 'image'],
            'relative root' => ['./logo.png', 'guildforge/logo', 'image'],
            'leading slash' => ['/events/a.jpg', 'guildforge/events/a', 'image'],
            'video' => ['videos/intro.mp4', 'guildforge/videos/intro', 'video'],
            'document' => ['docs/rules.pdf', 'guildforge/docs/rules', 'raw'],
            'unknown extension' => ['exports/data.zzz', 'guildforge/exports/data', 'raw'],
        ];
    }

    public function test_the_prefix_is_trimmed_and_optional(): void
    {
        $this->assertSame(['guildforge/a', 'image'], (new CloudinaryPathMapper('/guildforge/'))->toResource('a.jpg'));
        $this->assertSame(['events/a', 'image'], (new CloudinaryPathMapper(null))->toResource('events/a.jpg'));
        $this->assertSame(['events/a', 'image'], (new CloudinaryPathMapper(''))->toResource('events/a.jpg'));
    }

    public function test_folders_get_the_prefix(): void
    {
        $paths = new CloudinaryPathMapper('guildforge');

        $this->assertSame('guildforge/events/2026', $paths->prefixed('events/2026'));
        $this->assertSame('guildforge/events', $paths->prefixed('/events'));
        $this->assertSame('guildforge', $paths->prefixed(''));
        $this->assertSame('events', (new CloudinaryPathMapper(null))->prefixed('/events'));
    }

    public function test_it_detects_mime_types_from_the_extension(): void
    {
        $paths = new CloudinaryPathMapper('guildforge');

        $this->assertSame('image/jpeg', $paths->mimeType('events/a.jpg'));
        $this->assertNull($paths->mimeType('exports/data.zzz'));
    }
}
