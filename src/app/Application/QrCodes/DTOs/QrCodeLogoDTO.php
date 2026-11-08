<?php

declare(strict_types=1);

namespace App\Application\QrCodes\DTOs;

use App\Application\QrCodes\SvgLogoSanitizer;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeOptionsException;

/**
 * A logo drawn in the centre of a QR code: PNG, JPEG, WebP or SVG. It is given as bytes, never
 * as a path or a URL, so the renderer reads nothing from disk or the network on a module's
 * behalf. An SVG is sanitized here (SvgLogoSanitizer), so $contents only ever holds markup
 * that refers to nothing outside itself.
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

    public const string SVG_MIME_TYPE = 'image/svg+xml';

    /** @var array<int, string> */
    private const array MIME_TYPES = [
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    /** The raster bytes as given, or the sanitized SVG markup */
    public string $contents;

    public string $mimeType;

    /** In pixels; for an SVG only the proportion counts, with the longer side at MAX_DIMENSION */
    public int $width;

    public int $height;

    /**
     * @param  string  $contents  PNG, JPEG, WebP or SVG bytes
     * @param  float  $widthRatio  Width of the logo over the width of the code (margin excluded)
     */
    public function __construct(
        string $contents,
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

        [$this->contents, $this->mimeType, $this->width, $this->height] = $this->read($contents);
    }

    public function isSvg(): bool
    {
        return $this->mimeType === self::SVG_MIME_TYPE;
    }

    /**
     * @return array{string, string, int, int} Contents, MIME type, width and height
     */
    private function read(string $contents): array
    {
        // PHP 8.5's getimagesize() recognises SVG too (IMAGETYPE_SVG), so it is not a raster check
        $info = @getimagesizefromstring($contents);
        $isRaster = $info !== false && isset(self::MIME_TYPES[$info[2]]);

        if (! $isRaster && preg_match('/<svg[\s>\/]/i', $contents) === 1) {
            return $this->readSvg($contents);
        }

        if ($info === false || ! isset(self::MIME_TYPES[$info[2]])) {
            throw InvalidQrCodeOptionsException::because('the logo must be a PNG, JPEG, WebP or SVG image');
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw InvalidQrCodeOptionsException::because(sprintf(
                'the logo must be at most %1$dx%1$d pixels',
                self::MAX_DIMENSION,
            ));
        }

        return [$contents, self::MIME_TYPES[$info[2]], $width, $height];
    }

    /**
     * @return array{string, string, int, int}
     */
    private function readSvg(string $contents): array
    {
        $svg = SvgLogoSanitizer::sanitize($contents);

        if ($svg->width >= $svg->height) {
            return [$svg->contents, self::SVG_MIME_TYPE, self::MAX_DIMENSION, max(1, (int) round(self::MAX_DIMENSION * $svg->height / $svg->width))];
        }

        return [$svg->contents, self::SVG_MIME_TYPE, max(1, (int) round(self::MAX_DIMENSION * $svg->width / $svg->height)), self::MAX_DIMENSION];
    }
}
