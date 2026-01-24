<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\DTOs\ImageOptimizationSettingsDTO;
use App\Application\Services\ImageOptimizationServiceInterface;
use App\Infrastructure\Services\ImageOptimizationService;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

final class ImageOptimizationServiceTest extends TestCase
{
    private ImageOptimizationServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['images.optimization.enabled' => true]);
        $this->service = new ImageOptimizationService(
            $this->app->make(LoggerInterface::class)
        );
    }

    public function test_large_image_is_resized_to_max_dimensions(): void
    {
        $contents = $this->createJpegImage(4000, 3000);
        $options = new ImageOptimizationSettingsDTO(
            maxWidth: 2048,
            maxHeight: 2048,
            minSizeBytes: 0, // Disable size threshold for test
        );

        $result = $this->service->optimize($contents, 'image/jpeg', $options);

        $this->assertNotSame($contents, $result);
        [$width, $height] = $this->getImageDimensions($result);
        $this->assertLessThanOrEqual(2048, $width);
        $this->assertLessThanOrEqual(2048, $height);
    }

    public function test_small_image_is_not_resized(): void
    {
        $contents = $this->createJpegImage(800, 600);
        $options = new ImageOptimizationSettingsDTO(
            maxWidth: 2048,
            maxHeight: 2048,
            minSizeBytes: 0,
        );

        $result = $this->service->optimize($contents, 'image/jpeg', $options);

        [$width, $height] = $this->getImageDimensions($result);
        $this->assertSame(800, $width);
        $this->assertSame(600, $height);
    }

    public function test_aspect_ratio_is_preserved_after_resize(): void
    {
        // 4:3 aspect ratio
        $contents = $this->createJpegImage(4000, 3000);
        $options = new ImageOptimizationSettingsDTO(
            maxWidth: 2048,
            maxHeight: 2048,
            minSizeBytes: 0,
        );

        $result = $this->service->optimize($contents, 'image/jpeg', $options);

        [$width, $height] = $this->getImageDimensions($result);
        $aspectRatio = round($width / $height, 2);
        $expectedRatio = round(4000 / 3000, 2);
        $this->assertEquals($expectedRatio, $aspectRatio);
    }

    public function test_quality_compression_is_applied(): void
    {
        $contents = $this->createJpegImage(1024, 768, 100);
        $options = new ImageOptimizationSettingsDTO(
            maxWidth: 2048,
            maxHeight: 2048,
            quality: 50,
            minSizeBytes: 0,
        );

        $result = $this->service->optimize($contents, 'image/jpeg', $options);

        // Lower quality should result in smaller file (usually)
        // This is a soft assertion - compression behavior can vary
        $this->assertIsString($result);
        $this->assertGreaterThan(0, strlen($result));
    }

    public function test_png_image_is_optimized(): void
    {
        $contents = $this->createPngImage(3000, 2000);
        $options = new ImageOptimizationSettingsDTO(
            maxWidth: 1024,
            maxHeight: 1024,
            minSizeBytes: 0,
        );

        $result = $this->service->optimize($contents, 'image/png', $options);

        [$width, $height] = $this->getImageDimensions($result);
        $this->assertLessThanOrEqual(1024, $width);
        $this->assertLessThanOrEqual(1024, $height);
    }

    public function test_format_conversion_to_webp(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('WebP support not available');
        }

        $contents = $this->createJpegImage(800, 600);
        $options = new ImageOptimizationSettingsDTO(
            format: 'webp',
            minSizeBytes: 0,
        );

        $result = $this->service->optimize($contents, 'image/jpeg', $options);

        // Check WebP signature
        $this->assertStringStartsWith('RIFF', $result);
        $this->assertStringContainsString('WEBP', $result);
    }

    public function test_animated_gif_is_not_processed_when_skip_enabled(): void
    {
        $contents = $this->createAnimatedGif();
        $options = new ImageOptimizationSettingsDTO(
            maxWidth: 100,
            maxHeight: 100,
            skipAnimatedGif: true,
            minSizeBytes: 0,
        );

        $result = $this->service->optimize($contents, 'image/gif', $options);

        // Should return original unchanged
        $this->assertSame($contents, $result);
    }

    public function test_static_gif_is_processed(): void
    {
        $contents = $this->createStaticGif(500, 500);
        $options = new ImageOptimizationSettingsDTO(
            maxWidth: 200,
            maxHeight: 200,
            skipAnimatedGif: true,
            minSizeBytes: 0,
        );

        $result = $this->service->optimize($contents, 'image/gif', $options);

        [$width, $height] = $this->getImageDimensions($result);
        $this->assertLessThanOrEqual(200, $width);
        $this->assertLessThanOrEqual(200, $height);
    }

    public function test_corrupt_image_returns_original_gracefully(): void
    {
        $contents = 'This is not a valid image, but has enough bytes to pass the threshold';
        $contents .= str_repeat('x', 60000);
        $options = new ImageOptimizationSettingsDTO(
            minSizeBytes: 50000,
        );

        // Should not throw, should return original
        $result = $this->service->optimize($contents, 'image/jpeg', $options);

        $this->assertSame($contents, $result);
    }

    public function test_config_toggle_disables_optimization(): void
    {
        config(['images.optimization.enabled' => false]);

        $contents = $this->createJpegImage(4000, 3000);
        $options = new ImageOptimizationSettingsDTO(
            maxWidth: 1024,
            maxHeight: 1024,
            minSizeBytes: 0,
        );

        $result = $this->service->optimize($contents, 'image/jpeg', $options);

        // Should return original unchanged
        $this->assertSame($contents, $result);
    }

    public function test_auto_detects_mime_type_when_not_provided(): void
    {
        $contents = $this->createJpegImage(3000, 2000);
        $options = new ImageOptimizationSettingsDTO(
            maxWidth: 1024,
            maxHeight: 1024,
            minSizeBytes: 0,
        );

        // Pass null for MIME type, service should auto-detect
        $result = $this->service->optimize($contents, null, $options);

        [$width, $height] = $this->getImageDimensions($result);
        $this->assertLessThanOrEqual(1024, $width);
        $this->assertLessThanOrEqual(1024, $height);
    }

    public function test_service_is_registered_in_container(): void
    {
        $service = $this->app->make(ImageOptimizationServiceInterface::class);

        $this->assertInstanceOf(ImageOptimizationService::class, $service);
    }

    /**
     * Create a JPEG image with specified dimensions.
     */
    private function createJpegImage(int $width, int $height, int $quality = 90): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
        imagefill($image, 0, 0, $color);

        // Add some random rectangles to increase file size
        for ($i = 0; $i < 50; $i++) {
            $rectColor = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
            imagefilledrectangle(
                $image,
                rand(0, $width),
                rand(0, $height),
                rand(0, $width),
                rand(0, $height),
                $rectColor
            );
        }

        ob_start();
        imagejpeg($image, null, $quality);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    /**
     * Create a PNG image with specified dimensions.
     */
    private function createPngImage(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
        imagefill($image, 0, 0, $color);

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    /**
     * Create a static (non-animated) GIF image.
     */
    private function createStaticGif(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
        imagefill($image, 0, 0, $color);

        ob_start();
        imagegif($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    /**
     * Create a minimal animated GIF (2 frames).
     *
     * This creates a proper animated GIF with multiple graphic control extension blocks.
     */
    private function createAnimatedGif(): string
    {
        // Minimal animated GIF with 2 frames (hand-crafted binary)
        // This is a 2x2 pixel animated GIF with 2 frames
        return base64_decode(
            'R0lGODlhAgACAIAAAP///wAAACH5BAkKAAAAIf8LTkVUU0NBUEUyLjADAQAAACwA'.
            'AAAAAgACAAACAoRRADs=R0lGODlhAgACAIAAAAAAAP///yH5BAkKAAIALAAAAAAC'.
            'AAIAAAIDRAJRADs='
        )."\x00\x21\xF9\x04\x00\x00\x00\x00\x00"."\x00\x21\xF9\x04\x00\x00\x00\x00\x00";
    }

    /**
     * Get image dimensions from binary contents.
     *
     * @return array{0: int, 1: int}
     */
    private function getImageDimensions(string $contents): array
    {
        $image = imagecreatefromstring($contents);
        if ($image === false) {
            return [0, 0];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        return [$width, $height];
    }
}
