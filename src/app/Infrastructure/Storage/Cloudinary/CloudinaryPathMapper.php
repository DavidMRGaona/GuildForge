<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Cloudinary;

use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;

/**
 * Maps storage paths ("events/2026/01/uuid.jpg") to Cloudinary public ids
 * ("guildforge/events/2026/01/uuid") and resource types (image, video, raw).
 *
 * The prefix is applied to every resource type and never twice, as the
 * application's adapter always did; see CloudinaryStorageAdapter for the
 * license of the code this derives from.
 */
final readonly class CloudinaryPathMapper
{
    private string $prefix;

    public function __construct(?string $prefix = null, private MimeTypeDetector $mimeTypes = new FinfoMimeTypeDetector())
    {
        $this->prefix = $prefix !== null ? trim(str_replace('\\', '/', $prefix), '/') : '';
    }

    /**
     * @return array{0: string, 1: 'image'|'video'|'raw'}
     */
    public function toResource(string $path): array
    {
        $info = pathinfo($path);
        $dirname = str_replace('\\', '/', $info['dirname'] ?? '.');
        $publicId = $dirname !== '.' ? $dirname.'/'.$info['filename'] : $info['filename'];

        if ($this->prefix !== '' && ! str_starts_with($publicId, $this->prefix.'/')) {
            $normalized = ltrim($publicId, './\\/');
            $publicId = $this->prefix.($normalized !== '' ? '/'.$normalized : '');
        }

        $mimeType = $this->mimeType($path) ?? '';

        return match (true) {
            str_starts_with($mimeType, 'image/') => [$publicId, 'image'],
            str_starts_with($mimeType, 'video/') => [$publicId, 'video'],
            default => [$publicId, 'raw'],
        };
    }

    public function prefixed(string $path): string
    {
        $trimmed = ltrim(str_replace('\\', '/', $path), '/');

        if ($this->prefix === '') {
            return $trimmed;
        }

        return $this->prefix.($trimmed !== '' ? '/'.$trimmed : '');
    }

    public function mimeType(string $path): ?string
    {
        return $this->mimeTypes->detectMimeTypeFromPath($path);
    }
}
