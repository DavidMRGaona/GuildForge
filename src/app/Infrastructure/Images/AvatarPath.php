<?php

declare(strict_types=1);

namespace App\Infrastructure\Images;

use finfo;
use Illuminate\Support\Str;

/**
 * Storage paths of avatars on the "images" disk.
 *
 * Scheme: "<directory>/<uuid>.<ext>" for a static avatar and "<directory>/<uuid>-animated.<ext>"
 * for an animated one (more than one frame, see AnimatedImageDetector), where <ext> matches
 * the stored bytes (jpg, png, gif, webp, bmp or svg; anything else keeps the historical jpg).
 * The profile stores under "users/<user id>", the admin panel under "avatars/<Y>/<m>".
 *
 * The frontend only has this path (avatarPublicId) to choose the delivery: buildAvatarUrl()
 * in resources/js/utils/cloudinary.ts asks for animated delivery when the name ends with
 * "-animated". The marker is part of the Cloudinary public id (CloudinaryPathMapper and
 * normalizePublicId() only drop the extension) and has no dot on purpose: a dot left in
 * the public id would be read as a format by a delivery URL.
 *
 * Before 3.10.1 the profile stored "users/<user id>/<uuid>.jpg" and the panel
 * "avatars/<Y>/<m>/<uuid>.<extension sent by the browser>", whatever the bytes; only the
 * animated ones are renamed, by "php artisan users:mark-animated-avatars".
 */
final readonly class AvatarPath
{
    public const string ANIMATED_SUFFIX = '-animated';

    private const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/svg+xml' => 'svg',
    ];

    private const string FALLBACK_EXTENSION = 'jpg';

    public function __construct(
        private AnimatedImageDetector $animation = new AnimatedImageDetector,
    ) {}

    /**
     * Path for a new avatar with these (already optimized) bytes.
     */
    public function forUpload(string $directory, string $contents): string
    {
        return rtrim($directory, '/').'/'.$this->fileName(Str::uuid()->toString(), $contents);
    }

    /**
     * Path of a stored avatar once named after its bytes: same directory and name, with the
     * marker and extension these bytes call for.
     */
    public function forStored(string $path, string $contents): string
    {
        $info = pathinfo($path);
        $directory = $info['dirname'] ?? '.';
        $name = Str::chopEnd($info['filename'], self::ANIMATED_SUFFIX);
        $fileName = $this->fileName($name, $contents);

        return $directory !== '.' ? $directory.'/'.$fileName : $fileName;
    }

    /**
     * Whether a stored avatar path is marked as animated.
     */
    public static function isAnimated(string $path): bool
    {
        return str_ends_with(pathinfo($path, PATHINFO_FILENAME), self::ANIMATED_SUFFIX);
    }

    private function fileName(string $name, string $contents): string
    {
        $suffix = $this->animation->isAnimated($contents) ? self::ANIMATED_SUFFIX : '';

        return $name.$suffix.'.'.$this->extension($contents);
    }

    private function extension(string $contents): string
    {
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        return is_string($mimeType) ? (self::EXTENSIONS[$mimeType] ?? self::FALLBACK_EXTENSION) : self::FALLBACK_EXTENSION;
    }
}
