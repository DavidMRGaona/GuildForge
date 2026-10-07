<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Storage;

use App\Infrastructure\Storage\Cloudinary\CloudinaryStorageAdapter;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * URLs the "images" disk serves for stored paths.
 *
 * Every stored image path (events, articles, users, settings, modules) is turned
 * into a URL by these rules, so a change here means images stop showing. The
 * "_a" query is the Cloudinary SDK analytics token (SDK and PHP minor version):
 * it may change when either is upgraded; nothing else in these URLs may.
 */
final class CloudinaryImagesDiskTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.images.url' => 'cloudinary://key:secret@test-cloud',
            'filesystems.disks.images.prefix' => 'guildforge',
        ]);
        Storage::forgetDisk('images');
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('images');

        parent::tearDown();
    }

    public function test_the_disk_uses_the_application_adapter(): void
    {
        $this->assertInstanceOf(CloudinaryStorageAdapter::class, Storage::disk('images')->getAdapter());
    }

    #[DataProvider('urls')]
    public function test_urls_are_built_locally_and_never_change(string $path, string $url): void
    {
        $this->assertSame($url, Storage::disk('images')->url($path));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function urls(): array
    {
        $base = 'https://res.cloudinary.com/test-cloud';

        return [
            'image in a dated folder' => ['events/2026/01/3f2a.jpg', $base.'/image/upload/v1/guildforge/events/2026/01/3f2a?_a=BAAHWXH8'],
            'path that already carries the prefix' => ['guildforge/events/2026/01/3f2a.jpg', $base.'/image/upload/v1/guildforge/guildforge/events/2026/01/3f2a?_a=BAAHWXH8'],
            'webp image' => ['hero/slide.webp', $base.'/image/upload/v1/guildforge/hero/slide?_a=BAAHWXH8'],
            'file at the root' => ['logo.png', $base.'/image/upload/v1/guildforge/logo?_a=BAAHWXH8'],
            'document served as an image' => ['docs/rules.pdf', $base.'/image/upload/v1/guildforge/docs/rules?_a=BAAHWXH8'],
            'video' => ['videos/intro.mp4', $base.'/video/upload/v1/guildforge/videos/intro?_a=BAAHWXH8'],
            'space in the name' => ['users/avatars/a b.jpg', $base.'/image/upload/v1/guildforge/users/avatars/a%20b?_a=BAAHWXH8'],
        ];
    }
}
