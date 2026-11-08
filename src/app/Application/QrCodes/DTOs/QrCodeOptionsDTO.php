<?php

declare(strict_types=1);

namespace App\Application\QrCodes\DTOs;

use App\Application\QrCodes\Enums\QrCodeErrorCorrection;
use App\Application\QrCodes\HexColor;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeOptionsException;

/**
 * How a QR code is drawn. Every value is checked here, so a module cannot ask for an
 * image too large to render or a code that phones would fail to scan.
 */
final readonly class QrCodeOptionsDTO
{
    public const int MIN_SIZE = 128;

    public const int MAX_SIZE = 2048;

    /** WCAG AA for text; scanners need clear contrast too */
    public const float MIN_CONTRAST_RATIO = 4.5;

    /**
     * @param  int  $size  Width and height of the whole image in pixels, margin included
     * @param  int  $margin  Quiet zone around the code in pixels, at most a quarter of the size
     * @param  string  $foregroundColor  #RRGGBB of the modules; must be darker than the background
     * @param  string  $backgroundColor  #RRGGBB of the background and the margin
     * @param  QrCodeLogoDTO|null  $logo  Drawn in the centre; raises the error correction to High
     */
    public function __construct(
        public int $size = 512,
        public int $margin = 16,
        public QrCodeErrorCorrection $errorCorrection = QrCodeErrorCorrection::Medium,
        public string $foregroundColor = '#000000',
        public string $backgroundColor = '#FFFFFF',
        public ?QrCodeLogoDTO $logo = null,
    ) {
        if ($size < self::MIN_SIZE || $size > self::MAX_SIZE) {
            throw InvalidQrCodeOptionsException::because(sprintf(
                'the size must be between %d and %d pixels',
                self::MIN_SIZE,
                self::MAX_SIZE,
            ));
        }

        if ($margin < 0 || $margin > intdiv($size, 4)) {
            throw InvalidQrCodeOptionsException::because('the margin must be between 0 and a quarter of the size');
        }

        $foreground = HexColor::fromString($foregroundColor);
        $background = HexColor::fromString($backgroundColor);

        // Many scanners only read dark modules on a light background
        if ($foreground->relativeLuminance() >= $background->relativeLuminance()) {
            throw InvalidQrCodeOptionsException::because('the foreground colour must be darker than the background');
        }

        if ($foreground->contrastRatio($background) < self::MIN_CONTRAST_RATIO) {
            throw InvalidQrCodeOptionsException::because(sprintf(
                'the contrast between the colours is %.2f:1, below %.1f:1',
                $foreground->contrastRatio($background),
                self::MIN_CONTRAST_RATIO,
            ));
        }
    }

    public function foreground(): HexColor
    {
        return HexColor::fromString($this->foregroundColor);
    }

    public function background(): HexColor
    {
        return HexColor::fromString($this->backgroundColor);
    }

    /**
     * The level the code is drawn with: High whenever a logo covers part of it.
     */
    public function effectiveErrorCorrection(): QrCodeErrorCorrection
    {
        return $this->logo instanceof QrCodeLogoDTO ? QrCodeErrorCorrection::High : $this->errorCorrection;
    }
}
