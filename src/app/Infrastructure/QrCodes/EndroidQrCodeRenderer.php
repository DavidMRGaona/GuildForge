<?php

declare(strict_types=1);

namespace App\Infrastructure\QrCodes;

use App\Application\QrCodes\DTOs\QrCodeLogoDTO;
use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\DTOs\RenderedQrCodeDTO;
use App\Application\QrCodes\Enums\QrCodeErrorCorrection;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\QrCodes\HexColor;
use App\Application\QrCodes\Services\QrCodeRendererInterface;
use App\Domain\QrCodes\Exceptions\QrCodeRenderingException;
use DOMDocument;
use DOMElement;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use GdImage;
use RuntimeException;
use Throwable;

/**
 * QR codes with endroid/qr-code (on bacon/bacon-qr-code). PNG goes through GD, the only image
 * extension present in production, development and CI alike; an SVG logo is rasterized for it
 * by rsvg-convert first. The SVG output embeds any logo as a data URI image.
 */
final readonly class EndroidQrCodeRenderer implements QrCodeRendererInterface
{
    private const string SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const string TEMP_LOGO_PREFIX = 'guildforge-qr-logo-';

    /** Truecolor: endroid's default 16-colour palette makes GD shift the brand colours */
    private const array PNG_OPTIONS = [PngWriter::WRITER_OPTION_NUMBER_OF_COLORS => null];

    public function __construct(
        private QrCodeContentPolicy $policy,
        private RsvgConvertRasterizer $rasterizer = new RsvgConvertRasterizer,
    ) {}

    public function render(string $url, QrCodeOptionsDTO $options, QrCodeFormat $format): RenderedQrCodeDTO
    {
        $this->policy->assertAllowed($url);

        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $this->errorCorrectionLevel($options->effectiveErrorCorrection()),
            // endroid adds the margin around this size; the DTO size is the whole image
            size: $options->size - 2 * $options->margin,
            margin: $options->margin,
            // Whole-pixel blocks; the rounding goes to the margin, so the image keeps its size
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: $this->color($options->foreground()),
            backgroundColor: $this->color($options->background()),
        );

        try {
            $contents = match ($format) {
                QrCodeFormat::Svg => $this->svg($qrCode, $options),
                QrCodeFormat::Png => $this->png($qrCode, $options),
            };
        } catch (Throwable $exception) {
            throw QrCodeRenderingException::from($exception);
        }

        return new RenderedQrCodeDTO($contents, $format);
    }

    private function png(QrCode $qrCode, QrCodeOptionsDTO $options): string
    {
        if (! $options->logo instanceof QrCodeLogoDTO) {
            return (new PngWriter)->write($qrCode, options: self::PNG_OPTIONS)->getString();
        }

        // endroid reads logos from a path: a private temporary file, never a URL
        $path = tempnam(sys_get_temp_dir(), self::TEMP_LOGO_PREFIX);
        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary file for the logo');
        }

        try {
            [$width, $height] = $this->logoSize($options);

            // GD reads no SVG: draw it at the exact size it takes in the code
            $contents = $options->logo->isSvg()
                ? $this->rasterizer->rasterize($options->logo->contents, $width, $height)
                : $options->logo->contents;

            if (file_put_contents($path, $this->overBackground($contents, $options->background())) === false) {
                throw new RuntimeException('Cannot write the logo to a temporary file');
            }

            // Punchout: the modules under the logo become background, so it reads cleanly
            return (new PngWriter)
                ->write($qrCode, new Logo($path, $width, $height, punchoutBackground: true), options: self::PNG_OPTIONS)
                ->getString();
        } finally {
            @unlink($path);
        }
    }

    private function svg(QrCode $qrCode, QrCodeOptionsDTO $options): string
    {
        $svg = (new SvgWriter)
            ->write($qrCode, options: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true])
            ->getString();

        if (! $options->logo instanceof QrCodeLogoDTO) {
            return $svg;
        }

        // endroid's SVG writer has no punchout: draw a background patch, then the logo over it.
        // A data URI <image> keeps an SVG logo's styles and ids apart from the code's, and its
        // explicit size with the logo's viewBox keeps the proportion
        $document = new DOMDocument;
        if (! $document->loadXML($svg, LIBXML_NONET) || ! $document->documentElement instanceof DOMElement) {
            throw new RuntimeException('Cannot read the generated SVG');
        }

        [$width, $height] = $this->logoSize($options);
        $x = (string) intdiv($options->size - $width, 2);
        $y = (string) intdiv($options->size - $height, 2);

        $patch = $document->createElementNS(self::SVG_NAMESPACE, 'rect');
        $patch->setAttribute('x', $x);
        $patch->setAttribute('y', $y);
        $patch->setAttribute('width', (string) $width);
        $patch->setAttribute('height', (string) $height);
        $patch->setAttribute('fill', $options->background()->hex());

        $image = $document->createElementNS(self::SVG_NAMESPACE, 'image');
        $image->setAttribute('x', $x);
        $image->setAttribute('y', $y);
        $image->setAttribute('width', (string) $width);
        $image->setAttribute('height', (string) $height);
        $image->setAttribute('href', 'data:'.$options->logo->mimeType.';base64,'.base64_encode($options->logo->contents));

        $document->documentElement->appendChild($patch);
        $document->documentElement->appendChild($image);

        $result = $document->saveXML($document->documentElement);
        if ($result === false) {
            throw new RuntimeException('Cannot write the SVG');
        }

        return $result;
    }

    /**
     * The logo is a share of the code's width (margin excluded) and keeps its proportions.
     *
     * @return array{int, int}
     */
    private function logoSize(QrCodeOptionsDTO $options): array
    {
        $logo = $options->logo;
        if (! $logo instanceof QrCodeLogoDTO) {
            throw new RuntimeException('No logo to size');
        }

        $codeWidth = $options->size - 2 * $options->margin;
        $width = max(1, (int) round($codeWidth * $logo->widthRatio));
        $height = max(1, (int) round($width * $logo->height / $logo->width));

        // A tall logo is limited by the same share of the height
        $maxHeight = max(1, (int) round($codeWidth * QrCodeLogoDTO::MAX_WIDTH_RATIO));
        if ($height > $maxHeight) {
            $width = max(1, (int) round($width * $maxHeight / $height));
            $height = $maxHeight;
        }

        return [$width, $height];
    }

    private function errorCorrectionLevel(QrCodeErrorCorrection $level): ErrorCorrectionLevel
    {
        return match ($level) {
            QrCodeErrorCorrection::Low => ErrorCorrectionLevel::Low,
            QrCodeErrorCorrection::Medium => ErrorCorrectionLevel::Medium,
            QrCodeErrorCorrection::Quartile => ErrorCorrectionLevel::Quartile,
            QrCodeErrorCorrection::High => ErrorCorrectionLevel::High,
        };
    }

    private function color(HexColor $color): Color
    {
        return new Color($color->red, $color->green, $color->blue);
    }

    /**
     * The logo flattened over the code's background, as a PNG. endroid's PngWriter copies a
     * logo with GD without its alpha channel, so transparent pixels came out black: a logo
     * exported without a background (and every rasterized SVG) turned into a dark block.
     */
    private function overBackground(string $logo, HexColor $background): string
    {
        $source = imagecreatefromstring($logo);
        if (! $source instanceof GdImage) {
            throw new RuntimeException('The logo is not an image GD can read');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $fill = imagecolorallocate($canvas, $background->red, $background->green, $background->blue);
        if ($fill === false) {
            throw new RuntimeException('Cannot paint the logo background');
        }

        imagefilledrectangle($canvas, 0, 0, $width - 1, $height - 1, $fill);
        // Blend the logo over the colour by its alpha
        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        $written = imagepng($canvas);
        $png = (string) ob_get_clean();

        if (! $written || $png === '') {
            throw new RuntimeException('Cannot write the flattened logo');
        }

        return $png;
    }
}
