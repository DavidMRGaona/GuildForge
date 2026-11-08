<?php

declare(strict_types=1);

namespace Tests\Support\QrCodes;

use GdImage;
use RuntimeException;
use Zxing\QrReader;

/**
 * Small images and a decoder for the QR code tests, GD only.
 */
final class QrCodeTestImages
{
    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    public static function png(int $width, int $height): string
    {
        return self::encode($width, $height, static fn (GdImage $image): bool => imagepng($image));
    }

    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    public static function jpeg(int $width, int $height): string
    {
        return self::encode($width, $height, static fn (GdImage $image): bool => imagejpeg($image));
    }

    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    public static function webp(int $width, int $height): string
    {
        return self::encode($width, $height, static fn (GdImage $image): bool => imagewebp($image));
    }

    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    public static function gif(int $width, int $height): string
    {
        return self::encode($width, $height, static fn (GdImage $image): bool => imagegif($image));
    }

    /**
     * A solid SVG of the same red as the raster images, with the given viewBox size.
     */
    public static function svg(int $width, int $height, string $extra = ''): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d"><rect width="%1$d" height="%2$d" fill="#C81E1E"/>%3$s</svg>',
            $width,
            $height,
            $extra,
        );
    }

    /**
     * The text a QR code in a PNG decodes to, or null when it cannot be read.
     *
     * With GD even where Imagick is loaded, as on the CI runners: the decoder would pick Imagick
     * then, which needs about four times the memory (over 300 MB for a 2048 px code).
     */
    public static function decode(string $png): ?string
    {
        $text = (new QrReader($png, QrReader::SOURCE_TYPE_BLOB, useImagickIfAvailable: false))->text();

        return is_string($text) ? $text : null;
    }

    /**
     * @return array{int, int, int}
     */
    public static function pixel(string $png, int $x, int $y): array
    {
        $image = imagecreatefromstring($png);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Not an image');
        }

        $index = imagecolorat($image, $x, $y);
        if ($index === false) {
            throw new RuntimeException('No pixel there');
        }

        $rgb = imagecolorsforindex($image, $index);

        return [$rgb['red'], $rgb['green'], $rgb['blue']];
    }

    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     * @param  callable(GdImage): bool  $output
     */
    private static function encode(int $width, int $height, callable $output): string
    {
        $image = imagecreatetruecolor($width, $height);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Cannot create the test image');
        }

        $red = imagecolorallocate($image, 200, 30, 30);
        if ($red === false) {
            throw new RuntimeException('Cannot allocate the test colour');
        }
        imagefill($image, 0, 0, $red);

        ob_start();
        $output($image);

        return (string) ob_get_clean();
    }
}
