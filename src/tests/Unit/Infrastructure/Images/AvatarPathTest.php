<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Images;

use App\Infrastructure\Images\AvatarPath;
use App\Infrastructure\Storage\Cloudinary\CloudinaryPathMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Images\ImageFixtures;

/**
 * Avatars are stored under a name whose extension matches their bytes, and animated
 * ones carry "-animated" before it: the frontend only has the stored path to decide
 * whether to ask for animated delivery.
 */
final class AvatarPathTest extends TestCase
{
    private const string UUID = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /**
     * @return array<string, array{string, string}>
     */
    public static function uploads(): array
    {
        return [
            'static JPEG' => [ImageFixtures::jpeg(), '\.jpg'],
            'static PNG' => [ImageFixtures::png(), '\.png'],
            'static GIF' => [ImageFixtures::gif(1), '\.gif'],
            'static WebP' => [ImageFixtures::webp(), '\.webp'],
            'animated GIF' => [ImageFixtures::gif(2), '-animated\.gif'],
            'animated PNG' => [ImageFixtures::png(2), '-animated\.png'],
            'animated WebP' => [ImageFixtures::webp(2), '-animated\.webp'],
            'unknown bytes keep the historical extension' => ['not an image', '\.jpg'],
        ];
    }

    #[DataProvider('uploads')]
    public function test_a_new_avatar_is_named_after_its_bytes(string $contents, string $suffix): void
    {
        $path = (new AvatarPath)->forUpload('users/abc', $contents);

        $this->assertMatchesRegularExpression('#^users/abc'.self::UUID.$suffix.'$#', $path);
    }

    public function test_every_upload_gets_its_own_name(): void
    {
        $paths = new AvatarPath;

        $this->assertNotSame($paths->forUpload('users/abc', 'x'), $paths->forUpload('users/abc', 'x'));
    }

    public function test_a_trailing_slash_in_the_directory_is_not_doubled(): void
    {
        $this->assertStringStartsWith('avatars/2026/10/', (new AvatarPath)->forUpload('avatars/2026/10/', 'x'));
        $this->assertStringNotContainsString('//', (new AvatarPath)->forUpload('avatars/2026/10/', 'x'));
    }

    public function test_a_stored_animated_avatar_keeps_its_directory_and_name(): void
    {
        $path = (new AvatarPath)->forStored('users/abc/0d1c2b3a.jpg', ImageFixtures::gif(2));

        $this->assertSame('users/abc/0d1c2b3a-animated.gif', $path);
    }

    public function test_renaming_a_marked_avatar_again_does_not_repeat_the_marker(): void
    {
        $path = (new AvatarPath)->forStored('users/abc/0d1c2b3a-animated.gif', ImageFixtures::gif(2));

        $this->assertSame('users/abc/0d1c2b3a-animated.gif', $path);
    }

    public function test_only_a_trailing_marker_is_dropped_from_the_stored_name(): void
    {
        $path = (new AvatarPath)->forStored('users/abc/x-animated-y.jpg', ImageFixtures::gif(2));

        $this->assertSame('users/abc/x-animated-y-animated.gif', $path);
    }

    public function test_a_stored_avatar_without_directory_keeps_none(): void
    {
        $this->assertSame('0d1c2b3a-animated.webp', (new AvatarPath)->forStored('0d1c2b3a.jpg', ImageFixtures::webp(2)));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function storedPaths(): array
    {
        return [
            'marked GIF' => ['users/abc/0d1c2b3a-animated.gif', true],
            'marked WebP' => ['avatars/2026/10/0d1c2b3a-animated.webp', true],
            'historical JPEG name' => ['users/abc/0d1c2b3a.jpg', false],
            'static GIF' => ['users/abc/0d1c2b3a.gif', false],
            'marker in a directory only' => ['users/x-animated/0d1c2b3a.gif', false],
            'marker without extension' => ['users/abc/0d1c2b3a-animated', true],
        ];
    }

    #[DataProvider('storedPaths')]
    public function test_a_stored_path_says_whether_the_avatar_is_animated(string $path, bool $animated): void
    {
        $this->assertSame($animated, AvatarPath::isAnimated($path));
    }

    public function test_the_marker_is_part_of_the_cloudinary_public_id_without_dots(): void
    {
        $path = (new AvatarPath)->forStored('users/abc/0d1c2b3a.jpg', ImageFixtures::gif(2));

        [$publicId, $type] = (new CloudinaryPathMapper('guildforge'))->toResource($path);

        // Only the extension is dropped; no dot left that a delivery URL could read as a format
        $this->assertSame('guildforge/users/abc/0d1c2b3a-animated', $publicId);
        $this->assertSame('image', $type);
    }
}
