<?php

declare(strict_types=1);

namespace App\Infrastructure\Seo;

/**
 * Server-side counterpart of resources/js/utils/cloudinary.ts, limited to the
 * presets link previews, the panel media library and the profile card need. Keep the public ID normalization in sync.
 */
final readonly class CloudinaryImageUrlBuilder
{
    /** Where every image URL it builds lives; the content security policy allows images from it (core#776) */
    public const string HOST = 'res.cloudinary.com';

    public const int OG_WIDTH = 1200;

    public const int OG_HEIGHT = 630;

    public const int LOGO_SIZE = 600;

    private const string ANIMATED_AVATAR_SUFFIX = '-animated';

    private const array STRIPPED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico'];

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
     * A raster logo as a PNG that fits the box, for mail clients that do not show its format
     * (Outlook and WebP, most of them and AVIF). Not for SVG: see isVector().
     */
    public function emailLogo(string $publicId, int $height, int $maxWidth): ?string
    {
        return $this->build($publicId, "h_{$height},w_{$maxWidth},c_limit,f_png");
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
     * A banner cropped to the box around what Cloudinary finds most relevant, like the profile page's
     * buildImageUrl() with `crop: 'fill', gravity: 'auto'` (core#744).
     */
    public function banner(string $publicId, int $width, int $height): ?string
    {
        return $this->build($publicId, "w_{$width},h_{$height},c_fill,g_auto,q_auto,f_auto");
    }

    /**
     * A square PNG of the given size for the web app manifest (core#802), scaled up or down to fit.
     * Vector favicons are rasterized too: the manifest and iOS want bitmaps.
     */
    public function appIcon(string $publicId, int $size): ?string
    {
        return $this->build($publicId, "w_{$size},h_{$size},c_pad,f_png");
    }

    /**
     * The artwork fitted into `$artworkSize` and centred on a `$size` square of an opaque colour
     * (`#RRGGBB`), as a JPEG, which leaves no transparent pixel: maskable icons and the iOS home
     * screen one. Null for anything but a plain colour, so nothing else reaches the URL.
     */
    public function paddedAppIcon(string $publicId, int $size, int $artworkSize, string $background): ?string
    {
        if (preg_match('/^#([0-9A-Fa-f]{6})\z/', $background, $matches) !== 1) {
            return null;
        }

        $colour = 'b_rgb:'.strtoupper($matches[1]);

        return $this->build(
            $publicId,
            "w_{$artworkSize},h_{$artworkSize},c_pad,{$colour}/w_{$size},h_{$size},c_lpad,{$colour},q_90,f_jpg",
        );
    }

    /**
     * Square thumbnail of a media library file (an id relative to the prefix, without extension),
     * cropped around what Cloudinary finds most relevant. An SVG is served as uploaded (see isVector()).
     */
    public function mediaThumbnail(string $fileId, int $size, ?string $format = null): ?string
    {
        return $this->buildMedia($fileId, 'image', $this->isVector($format) ? null : "w_{$size},h_{$size},c_fill,g_auto,q_auto,f_auto");
    }

    /**
     * A media library file scaled down to the given width at most, uncropped.
     */
    public function mediaPreview(string $fileId, int $width, ?string $format = null): ?string
    {
        return $this->buildMedia($fileId, 'image', $this->isVector($format) ? null : "w_{$width},c_limit,q_auto,f_auto");
    }

    /**
     * A media library file scaled down to the given height at most, uncropped, in the best format and
     * quality for the browser (logos). An SVG is served as uploaded (see isVector()).
     */
    public function mediaHeightLimited(string $fileId, int $height, ?string $format = null): ?string
    {
        return $this->buildMedia($fileId, 'image', $this->isVector($format) ? null : "h_{$height},c_limit,q_auto,f_auto");
    }

    /**
     * The file as it was uploaded.
     */
    public function mediaOriginal(string $fileId, string $resourceType = 'image'): ?string
    {
        return $this->buildMedia($fileId, $resourceType, null);
    }

    /**
     * Browsers draw an SVG at any size, and Cloudinary refuses to rasterize some that browsers accept
     * (a width such as "210mmpx" answers 400 «Invalid image file»), so vector files skip the transformations.
     */
    private function isVector(?string $format): bool
    {
        return $format !== null && strtolower($format) === 'svg';
    }

    /**
     * Library ids are always relative to the prefix, so it is always added (an id may itself start with
     * the prefix's name), and each segment is encoded.
     */
    private function buildMedia(string $fileId, string $resourceType, ?string $transformations): ?string
    {
        if ($this->cloudName === '' || $fileId === '' || preg_match('/^[a-z]+\z/', $resourceType) !== 1) {
            return null;
        }

        $path = implode('/', array_map(rawurlencode(...), explode('/', $fileId)));

        return sprintf(
            'https://%s/%s/%s/upload/%s%s',
            self::HOST,
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
            'https://%s/%s/image/upload/%s/%s',
            self::HOST,
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
