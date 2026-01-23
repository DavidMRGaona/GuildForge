<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Services\ImageOptimizationServiceInterface;
use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Cloudinary;
use CloudinaryLabs\CloudinaryLaravel\CloudinaryStorageAdapter as BaseCloudinaryStorageAdapter;
use League\Flysystem\Config;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;

/**
 * Cloudinary storage adapter for Laravel's filesystem.
 *
 * Fixes and enhancements over the base adapter:
 * - Applies prefix consistently for all file types (base only does it for raw files)
 * - Generates URLs directly without Admin API calls (faster, more reliable)
 * - Handles "not found" errors gracefully on delete
 * - Supports Dynamic Folder Mode with asset_folder parameter
 * - Optimizes images before upload (resize/compress) to reduce bandwidth
 */
final class CloudinaryStorageAdapter extends BaseCloudinaryStorageAdapter
{
    private Cloudinary $cloudinaryInstance;

    private string $prefixPath;

    private MimeTypeDetector $mimeDetector;

    public function __construct(
        Cloudinary $cloudinary,
        ?MimeTypeDetector $mimeTypeDetector = null,
        ?string $prefix = null,
        private ?ImageOptimizationServiceInterface $imageOptimization = null,
    ) {
        parent::__construct($cloudinary, $mimeTypeDetector, $prefix);
        $this->cloudinaryInstance = $cloudinary;
        $this->prefixPath = $prefix !== null ? trim($prefix, '/') : '';
        $this->mimeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
    }

    /**
     * Override prepareResource to ALWAYS apply the prefix for all file types.
     * Parent class only applies a prefix for "raw" files, not images/videos.
     *
     * @return array{0: string, 1: string} [publicId, resourceType]
     */
    public function prepareResource(string $path): array
    {
        $info = pathinfo($path);
        $dirname = str_replace('\\', '/', $info['dirname'] ?? '.');
        $filename = $info['filename'];

        // Build public_id: dirname/filename (no extension)
        $publicId = $dirname !== '.' ? $dirname . '/' . $filename : $filename;

        // Apply prefix if configured AND the path doesn't already start with it
        // (Laravel's FilesystemAdapter adds prefix for some operations but not others)
        if ($this->prefixPath !== '' && !str_starts_with($publicId, $this->prefixPath . '/')) {
            $normalizedId = ltrim($publicId, './\\/');
            $publicId = $this->prefixPath . ($normalizedId !== '' ? '/' . $normalizedId : '');
        }

        // Detect a resource type
        $mimeType = $this->mimeDetector->detectMimeTypeFromPath($path);

        if (str_starts_with($mimeType ?? '', 'image/')) {
            return [$publicId, 'image'];
        }

        if (str_starts_with($mimeType ?? '', 'video/')) {
            return [$publicId, 'video'];
        }

        return [$publicId, 'raw'];
    }

    public function delete(string $path): void
    {
        try {
            parent::delete($path);
        } catch (NotFound) {
            // File doesn't exist in Cloudinary, ignore the error
        }
    }

    /**
     * Override writing to add asset_folder parameter for Dynamic Folder Mode.
     * This ensures assets are organized into folders in the Media Library.
     * Also optimizes images before upload to reduce bandwidth.
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $mimeType = $this->mimeDetector->detectMimeTypeFromPath($path);

        // Optimize image before upload if service is available
        if ($this->imageOptimization !== null) {
            $contents = $this->imageOptimization->optimize($contents, $mimeType);
        }

        [$publicId, $type] = $this->prepareResource($path);
        $assetFolder = $this->extractFolder($publicId);

        // Convert binary content to data URI for Cloudinary upload
        $dataUri = $this->toDataUri($contents, $mimeType);

        $this->cloudinaryInstance->uploadApi()->upload($dataUri, [
            'public_id' => $publicId,
            'resource_type' => $type,
            'asset_folder' => $assetFolder,
        ]);
    }

    /**
     * Override writeStream to add asset_folder parameter for Dynamic Folder Mode.
     * Also optimizes images before upload.
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        // Convert stream to string for optimization
        $stringContents = is_resource($contents) ? stream_get_contents($contents) : (string) $contents;

        if ($stringContents === false) {
            $stringContents = '';
        }

        $mimeType = $this->mimeDetector->detectMimeTypeFromPath($path);

        // Optimize image before upload if service is available
        if ($this->imageOptimization !== null) {
            $stringContents = $this->imageOptimization->optimize($stringContents, $mimeType);
        }

        [$publicId, $type] = $this->prepareResource($path);
        $assetFolder = $this->extractFolder($publicId);

        // Convert binary content to data URI for Cloudinary upload
        $dataUri = $this->toDataUri($stringContents, $mimeType);

        $this->cloudinaryInstance->uploadApi()->upload($dataUri, [
            'public_id' => $publicId,
            'resource_type' => $type,
            'asset_folder' => $assetFolder,
        ]);
    }

    /**
     * Convert binary content to a data URI for Cloudinary upload.
     */
    private function toDataUri(string $contents, ?string $mimeType): string
    {
        $mimeType ??= 'application/octet-stream';

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    /**
     * Extract the folder path from public_id.
     * e.g., "guildforge/events/2026/01/uuid" -> "guildforge/events/2026/01"
     */
    private function extractFolder(string $publicId): string
    {
        $lastSlash = strrpos($publicId, '/');
        if ($lastSlash === false) {
            return '';
        }

        return substr($publicId, 0, $lastSlash);
    }

    /**
     * Generate URL directly using Cloudinary SDK instead of Admin API.
     * This is faster and doesn't fail if the image temporarily doesn't exist.
     */
    public function getUrl(string $path): string
    {
        [$publicId, $type] = $this->prepareResource($path);

        if ($type === 'video') {
            return (string) $this->cloudinaryInstance->video($publicId)->toUrl();
        }

        return (string) $this->cloudinaryInstance->image($publicId)->toUrl();
    }
}
