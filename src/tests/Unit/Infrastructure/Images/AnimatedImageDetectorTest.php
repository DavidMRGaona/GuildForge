<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Images;

use App\Infrastructure\Images\AnimatedImageDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Images\ImageFixtures;

/**
 * An image is animated when it holds more than one frame, read from its bytes: the
 * MIME type or file name an upload claims is never trusted.
 */
final class AnimatedImageDetectorTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function animatedImages(): array
    {
        return [
            'GIF with two frames' => [ImageFixtures::gif(2)],
            'GIF without loop block whose first control block follows a non-zero byte' => [ImageFixtures::gif(2, loop: false)],
            'GIF whose colour table ends with zero' => [ImageFixtures::gif(2, colorTable: "\xFF\xFF\xFF\x00\x00\x00")],
            'GIF frames without control blocks' => [ImageFixtures::gif(3, graphicControl: false)],
            'APNG with two frames' => [ImageFixtures::png(2)],
            'animated WebP with two frames' => [ImageFixtures::webp(2)],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function staticImages(): array
    {
        return [
            'GIF with one frame' => [ImageFixtures::gif(1)],
            'GIF with one frame and no control block' => [ImageFixtures::gif(1, graphicControl: false)],
            'PNG' => [ImageFixtures::png()],
            'APNG announcing a single frame' => [ImageFixtures::png(1)],
            'PNG with an animation chunk after its image data' => [ImageFixtures::png(2, animationControlAfterData: true)],
            'simple WebP' => [ImageFixtures::webp()],
            'extended WebP with a single frame' => [ImageFixtures::webp(1)],
            'JPEG' => [ImageFixtures::jpeg()],
            'empty string' => [''],
            'not an image' => ['plain text that happens to mention GIF89a and acTL'],
            'truncated GIF header' => ['GIF89a'],
            'truncated PNG' => ["\x89PNG\r\n\x1A\n\x00\x00"],
            'truncated WebP' => ['RIFF'.pack('V', 4).'WEBP'],
        ];
    }

    #[DataProvider('animatedImages')]
    public function test_an_image_with_several_frames_is_animated(string $contents): void
    {
        $this->assertTrue((new AnimatedImageDetector)->isAnimated($contents));
    }

    #[DataProvider('staticImages')]
    public function test_an_image_with_one_frame_or_none_is_not_animated(string $contents): void
    {
        $this->assertFalse((new AnimatedImageDetector)->isAnimated($contents));
    }

    public function test_a_gif_cut_after_its_second_frame_starts_is_still_animated(): void
    {
        $gif = ImageFixtures::gif(2);

        $this->assertTrue((new AnimatedImageDetector)->isAnimated(substr($gif, 0, -8)));
    }

    public function test_bytes_after_the_gif_trailer_are_not_frames(): void
    {
        $gif = ImageFixtures::gif(1).ImageFixtures::gif(1);

        $this->assertFalse((new AnimatedImageDetector)->isAnimated($gif));
    }
}
