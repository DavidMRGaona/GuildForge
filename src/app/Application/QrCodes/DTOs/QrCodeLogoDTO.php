<?php

declare(strict_types=1);

namespace App\Application\QrCodes\DTOs;

use App\Domain\QrCodes\Exceptions\InvalidQrCodeOptionsException;

/**
 * A raster logo drawn in the centre of a QR code. It is given as bytes, never as a path or a
 * URL, so the renderer reads nothing from disk or the network on a module's behalf.
 */
final readonly class QrCodeLogoDTO
{
    public const int MAX_BYTES = 1_048_576;

    /** Width and height in pixels; larger images are a decompression bomb risk */
    public const int MAX_DIMENSION = 2048;

    public const float MIN_WIDTH_RATIO = 0.10;

    /** A quarter of the width is about 6 % of the area, well inside what level H recovers */
    public const float MAX_WIDTH_RATIO = 0.25;

    public const float DEFAULT_WIDTH_RATIO = 0.20;

    /** @var array<int, string> */
    private const array MIME_TYPES = [
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    public string $mimeType;

    public int $width;

    public int $height;

    /**
     * @param  string  $contents  PNG, JPEG or WebP bytes
     * @param  float  $widthRatio  Width of the logo over the width of the code (margin excluded)
     */
    public function __construct(
        public string $contents,
        public float $widthRatio = self::DEFAULT_WIDTH_RATIO,
    ) {
        if (is_nan($widthRatio) || $widthRatio < self::MIN_WIDTH_RATIO || $widthRatio > self::MAX_WIDTH_RATIO) {
            throw InvalidQrCodeOptionsException::because(sprintf(
                'the logo width ratio must be between %.2f and %.2f',
                self::MIN_WIDTH_RATIO,
                self::MAX_WIDTH_RATIO,
            ));
        }

        if ($contents === '') {
            throw InvalidQrCodeOptionsException::because('the logo is empty');
        }

        if (strlen($contents) > self::MAX_BYTES) {
            throw InvalidQrCodeOptionsException::because(sprintf('the logo is larger than %d bytes', self::MAX_BYTES));
        }

        $info = @getimagesizefromstring($contents);
        if ($info === false || ! isset(self::MIME_TYPES[$info[2]])) {
            throw InvalidQrCodeOptionsException::because('the logo must be a PNG, JPEG or WebP image');
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw InvalidQrCodeOptionsException::because(sprintf(
                'the logo must be at most %1$dx%1$d pixels',
                self::MAX_DIMENSION,
            ));
        }

        $this->mimeType = self::MIME_TYPES[$info[2]];
        $this->width = $width;
        $this->height = $height;
    }
}
