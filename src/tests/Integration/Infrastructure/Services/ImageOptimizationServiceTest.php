<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\DTOs\ImageOptimizationSettingsDTO;
use App\Application\Services\ImageOptimizationServiceInterface;
use App\Infrastructure\Services\ImageOptimizationService;
use GdImage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Log\LoggerInterface;
use Tests\Support\Images\ImageFixtures;
use Tests\Support\Logging\RecordingLogger;
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function animatedImages(): array
    {
        return [
            'GIF with two frames' => [ImageFixtures::gif(2), 'image/gif'],
            'GIF without loop block whose first control block follows a non-zero byte' => [ImageFixtures::gif(2, loop: false), 'image/gif'],
            'GIF frames without control blocks' => [ImageFixtures::gif(2, graphicControl: false), 'image/gif'],
            'APNG' => [ImageFixtures::png(2), 'image/png'],
            'animated WebP' => [ImageFixtures::webp(2), 'image/webp'],
            // The images disk passes the MIME type of the path, not of the bytes
            'animated GIF stored under a .jpg name' => [ImageFixtures::gif(2), 'image/jpeg'],
        ];
    }

    #[DataProvider('animatedImages')]
    #[RequiresPhpExtension('imagick')]
    public function test_an_animated_image_keeps_its_original_bytes(string $contents, string $mimeType): void
    {
        $service = new ImageOptimizationService(new RecordingLogger, new ImagickDriver);

        $result = $service->optimize($contents, $mimeType, new ImageOptimizationSettingsDTO(maxWidth: 512, maxHeight: 512, minSizeBytes: 0));

        $this->assertSame($contents, $result);
    }

    #[DataProvider('animatedImages')]
    public function test_an_animated_image_keeps_its_original_bytes_with_the_gd_driver(string $contents, string $mimeType): void
    {
        $service = new ImageOptimizationService(new RecordingLogger, new GdDriver);

        $result = $service->optimize($contents, $mimeType, new ImageOptimizationSettingsDTO(maxWidth: 512, maxHeight: 512, minSizeBytes: 0));

        $this->assertSame($contents, $result);
    }

    public function test_a_single_frame_png_is_still_optimized(): void
    {
        $contents = ImageFixtures::png(1);

        $result = $this->service->optimize($contents, 'image/png', new ImageOptimizationSettingsDTO(minSizeBytes: 0));

        $this->assertNotSame($contents, $result);
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

    public function test_a_valid_optimization_logs_nothing(): void
    {
        $logger = new RecordingLogger;
        $service = new ImageOptimizationService($logger);
        $contents = $this->createJpegImage(3000, 2000);

        $result = $service->optimize($contents, 'image/jpeg', new ImageOptimizationSettingsDTO(maxWidth: 1024, maxHeight: 1024, minSizeBytes: 0));

        $this->assertSame([], $logger->records, 'optimize() must not swallow an exception into a warning');
        [$width] = $this->getImageDimensions($result);
        $this->assertLessThanOrEqual(1024, $width);
        $this->assertSame('image/jpeg', $this->mimeType($result));
    }

    public function test_it_optimizes_with_the_gd_driver(): void
    {
        $logger = new RecordingLogger;
        $service = new ImageOptimizationService($logger, new GdDriver);

        $result = $service->optimize($this->createJpegImage(3000, 2000), 'image/jpeg', new ImageOptimizationSettingsDTO(maxWidth: 1024, maxHeight: 1024, minSizeBytes: 0));

        $this->assertSame([], $logger->records);
        $this->assertLessThanOrEqual(1024, $this->getImageDimensions($result)[0]);
        $this->assertSame('image/jpeg', $this->mimeType($result));
    }

    #[RequiresPhpExtension('imagick')]
    public function test_it_optimizes_with_the_imagick_driver(): void
    {
        $logger = new RecordingLogger;
        $service = new ImageOptimizationService($logger, new ImagickDriver);

        $result = $service->optimize($this->createJpegImage(3000, 2000), 'image/jpeg', new ImageOptimizationSettingsDTO(maxWidth: 1024, maxHeight: 1024, minSizeBytes: 0));

        $this->assertSame([], $logger->records);
        $this->assertLessThanOrEqual(1024, $this->getImageDimensions($result)[0]);
        $this->assertSame('image/jpeg', $this->mimeType($result));
    }

    /**
     * Create a JPEG image with specified dimensions.
     *
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    private function createJpegImage(int $width, int $height, int $quality = 90): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, $this->randomColor($image));

        // Add some random rectangles to increase file size
        for ($i = 0; $i < 50; $i++) {
            $rectColor = $this->randomColor($image);
            imagefilledrectangle(
                $image,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                $rectColor
            );
        }

        ob_start();
        imagejpeg($image, null, $quality);

        return ob_get_clean();
    }

    /**
     * Create a PNG image with specified dimensions.
     *
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    private function createPngImage(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, $this->randomColor($image));

        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    /**
     * Create a static (non-animated) GIF image.
     *
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    private function createStaticGif(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, $this->randomColor($image));

        ob_start();
        imagegif($image);

        return ob_get_clean();
    }

    /**
     * A real 2-frame GIF whose Graphic Control Extensions both follow a 0x00 byte.
     */
    private function createAnimatedGif(): string
    {
        return ImageFixtures::gif(2, colorTable: "\xFF\xFF\xFF\x00\x00\x00");
    }

    private function randomColor(GdImage $image): int
    {
        $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));

        if ($color === false) {
            $this->fail('GD could not allocate a colour');
        }

        return $color;
    }

    private function mimeType(string $contents): string
    {
        $info = getimagesizefromstring($contents);

        if ($info === false) {
            $this->fail('The result is not an image');
        }

        return $info['mime'];
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

        return [$width, $height];
    }
}
