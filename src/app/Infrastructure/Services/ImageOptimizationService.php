<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\ImageOptimizationSettingsDTO;
use App\Application\Services\ImageOptimizationServiceInterface;
use finfo;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for optimizing images before upload using Intervention Image.
 *
 * Features:
 * - Resizes images that exceed maximum dimensions (preserves aspect ratio)
 * - Compresses images with configurable quality
 * - Optional format conversion
 * - Skips animated GIFs to preserve animation
 * - Graceful fallback to original image on any error
 * - Prefers Imagick driver, falls back to GD
 */
final class ImageOptimizationService implements ImageOptimizationServiceInterface
{
    private const array OPTIMIZABLE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private ImageManager $imageManager;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        $this->imageManager = $this->createImageManager();
    }

    public function optimize(string $contents, ?string $mimeType = null, ?ImageOptimizationSettingsDTO $settings = null): string
    {
        $settings ??= ImageOptimizationSettingsDTO::fromConfig();

        // Skip if disabled
        if (! $this->isEnabled()) {
            return $contents;
        }

        // Auto-detect MIME type if not provided
        $mimeType ??= $this->detectMimeType($contents);

        // Skip non-optimizable types
        if (! $this->isOptimizableMimeType($mimeType)) {
            return $contents;
        }

        // Skip small files
        $size = strlen($contents);
        if ($size < $settings->minSizeBytes) {
            return $contents;
        }

        // Skip animated GIFs if configured
        if ($settings->skipAnimatedGif && $this->isAnimatedGif($contents, $mimeType)) {
            return $contents;
        }

        // At this point, mimeType is guaranteed to be non-null because isOptimizableMimeType() returns false for null
        assert($mimeType !== null);

        try {
            return $this->processImage($contents, $mimeType, $settings);
        } catch (Throwable $e) {
            $this->logger->warning('Image optimization failed, returning original', [
                'error' => $e->getMessage(),
                'mime_type' => $mimeType,
                'size' => $size,
            ]);

            return $contents;
        }
    }

    public function isEnabled(): bool
    {
        return (bool) config('images.optimization.enabled', true);
    }

    public function isOptimizableMimeType(?string $mimeType): bool
    {
        if ($mimeType === null) {
            return false;
        }

        return in_array(strtolower($mimeType), self::OPTIMIZABLE_MIME_TYPES, true);
    }

    /**
     * Process and optimize the image.
     */
    private function processImage(string $contents, string $mimeType, ImageOptimizationSettingsDTO $settings): string
    {
        $image = $this->imageManager->read($contents);

        // Get original dimensions
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Only resize if exceeds limits (scaleDown preserves aspect ratio)
        if ($originalWidth > $settings->maxWidth || $originalHeight > $settings->maxHeight) {
            $image = $image->scaleDown($settings->maxWidth, $settings->maxHeight);
        }

        // Encode with appropriate encoder
        $encoder = $this->getEncoder($mimeType, $settings);
        $encoded = $image->encode($encoder);

        return (string) $encoded;
    }

    /**
     * Get the appropriate encoder based on settings and MIME type.
     */
    private function getEncoder(string $mimeType, ImageOptimizationSettingsDTO $settings): EncoderInterface
    {
        // If format conversion is requested, use that format
        $targetFormat = $settings->format ?? $this->mimeTypeToFormat($mimeType);

        return match ($targetFormat) {
            'jpg', 'jpeg' => new JpegEncoder($settings->quality),
            'png' => new PngEncoder(),
            'webp' => new WebpEncoder($settings->quality),
            'gif' => new GifEncoder(),
            // AutoEncoder auto-detects format from image, quality is set via separate encoders
            default => new AutoEncoder(),
        };
    }

    /**
     * Convert MIME type to format string.
     */
    private function mimeTypeToFormat(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpeg',
        };
    }

    /**
     * Detect MIME type from file contents.
     */
    private function detectMimeType(string $contents): ?string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents);

        return $mimeType !== false ? $mimeType : null;
    }

    /**
     * Check if the content is an animated GIF.
     */
    private function isAnimatedGif(string $contents, ?string $mimeType): bool
    {
        if ($mimeType !== 'image/gif') {
            return false;
        }

        // Check for multiple frame markers in GIF
        // Animated GIFs have multiple image separators (0x00 0x2C)
        $frameCount = preg_match_all('/\x00\x21\xF9\x04/', $contents);

        return $frameCount > 1;
    }

    /**
     * Create the ImageManager with the best available driver.
     */
    private function createImageManager(): ImageManager
    {
        // Prefer Imagick for better quality and performance
        if (extension_loaded('imagick')) {
            return new ImageManager(new ImagickDriver());
        }

        // Fall back to GD
        return new ImageManager(new GdDriver());
    }
}
