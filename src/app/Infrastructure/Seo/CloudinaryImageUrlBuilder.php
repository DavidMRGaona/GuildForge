<?php

declare(strict_types=1);

namespace App\Infrastructure\Seo;

/**
 * Server-side counterpart of resources/js/utils/cloudinary.ts, limited to the
 * presets link previews and the panel media library need. Keep the public ID normalization in sync.
 */
final readonly class CloudinaryImageUrlBuilder
{
    public const int OG_WIDTH = 1200;

    public const int OG_HEIGHT = 630;

    public const int LOGO_SIZE = 600;

    private const string ANIMATED_AVATAR_SUFFIX = '-animated';

    private const array STRIPPED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];

    public function __construct(
        private string $cloudName,
        private string $prefix,
    ) {}

    /**
     * Large preview image. JPG on purpose: WhatsApp does not reliably render
     * WebP/AVIF, which f_auto may serve. Uniform borders (a poster uploaded with
     * padding bands) are trimmed first, as the client-side card and hero do.
     */
    public function openGraph(string $publicId): ?string
    {
        return $this->build($publicId, 'e_trim/w_'.self::OG_WIDTH.',h_'.self::OG_HEIGHT.',c_fill,g_auto,q_auto,f_jpg');
    }

    /**
     * Square logo padded on white (black for a logo drawn for dark themes),
     * for the small "summary" card.
     */
    public function logo(string $publicId, bool $darkBackground = false): ?string
    {
        $background = $darkBackground ? 'b_black' : 'b_white';

        return $this->build($publicId, 'w_'.self::LOGO_SIZE.',h_'.self::LOGO_SIZE.",c_pad,{$background},q_auto,f_png");
    }

    /**
     * Square avatar cropped around the face, like the client's buildAvatarUrl(). An avatar whose
     * name ends in "-animated" keeps its animation.
     */
    public function avatar(string $publicId, int $size): ?string
    {
        $fileName = basename($publicId);
        $lastDot = strrpos($fileName, '.');
        $baseName = $lastDot !== false && $lastDot > 0 ? substr($fileName, 0, $lastDot) : $fileName;
        $animated = str_ends_with($baseName, self::ANIMATED_AVATAR_SUFFIX) ? ',fl_animated' : '';

        return $this->build($publicId, "w_{$size},h_{$size},c_fill,g_face,q_auto,f_auto{$animated}");
    }

    /**
     * Square thumbnail of a media library file (an id relative to the prefix, without extension),
     * cropped around what Cloudinary finds most relevant.
     */
    public function mediaThumbnail(string $fileId, int $size): ?string
    {
        return $this->buildMedia($fileId, 'image', "w_{$size},h_{$size},c_fill,g_auto,q_auto,f_auto");
    }

    /**
     * A media library file scaled down to the given width at most, uncropped.
     */
    public function mediaPreview(string $fileId, int $width): ?string
    {
        return $this->buildMedia($fileId, 'image', "w_{$width},c_limit,q_auto,f_auto");
    }

    /**
     * The file as it was uploaded.
     */
    public function mediaOriginal(string $fileId, string $resourceType = 'image'): ?string
    {
        return $this->buildMedia($fileId, $resourceType, null);
    }

    /**
     * Library ids are always relative to the prefix, so it is always added (an id may itself start with
     * the prefix's name), and each segment is encoded.
     */
    private function buildMedia(string $fileId, string $resourceType, ?string $transformations): ?string
    {
        if ($this->cloudName === '' || $fileId === '' || preg_match('/^[a-z]+$/', $resourceType) !== 1) {
            return null;
        }

        $path = implode('/', array_map(rawurlencode(...), explode('/', $fileId)));

        return sprintf(
            'https://res.cloudinary.com/%s/%s/upload/%s%s',
            $this->cloudName,
            $resourceType,
            $transformations !== null ? $transformations.'/' : '',
            $this->prefix !== '' ? $this->prefix.'/'.$path : $path,
        );
    }

    private function build(string $publicId, string $transformations): ?string
    {
        if ($this->cloudName === '' || $publicId === '') {
            return null;
        }

        return sprintf(
            'https://res.cloudinary.com/%s/image/upload/%s/%s',
            $this->cloudName,
            $transformations,
            $this->normalizePublicId($publicId),
        );
    }

    private function normalizePublicId(string $publicId): string
    {
        $lastDot = strrpos($publicId, '.');

        if ($lastDot !== false && $lastDot > 0) {
            $extension = strtolower(substr($publicId, $lastDot + 1));

            if (in_array($extension, self::STRIPPED_EXTENSIONS, true)) {
                $publicId = substr($publicId, 0, $lastDot);
            }
        }

        if ($this->prefix !== '' && ! str_starts_with($publicId, $this->prefix.'/')) {
            return $this->prefix.'/'.$publicId;
        }

        return $publicId;
    }
}
