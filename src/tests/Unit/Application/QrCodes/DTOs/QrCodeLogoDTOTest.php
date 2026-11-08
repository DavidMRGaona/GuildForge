<?php

declare(strict_types=1);

namespace Tests\Unit\Application\QrCodes\DTOs;

use App\Application\QrCodes\DTOs\QrCodeLogoDTO;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeOptionsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\QrCodes\QrCodeTestImages;

final class QrCodeLogoDTOTest extends TestCase
{
    public function test_it_reads_a_png_logo(): void
    {
        $logo = new QrCodeLogoDTO(QrCodeTestImages::png(120, 60));

        $this->assertSame('image/png', $logo->mimeType);
        $this->assertSame(120, $logo->width);
        $this->assertSame(60, $logo->height);
        $this->assertSame(QrCodeLogoDTO::DEFAULT_WIDTH_RATIO, $logo->widthRatio);
    }

    public function test_it_reads_jpeg_and_webp_logos(): void
    {
        $this->assertSame('image/jpeg', (new QrCodeLogoDTO(QrCodeTestImages::jpeg(40, 40)))->mimeType);
        $this->assertSame('image/webp', (new QrCodeLogoDTO(QrCodeTestImages::webp(40, 40)))->mimeType);
    }

    public function test_it_accepts_the_ratio_bounds(): void
    {
        $png = QrCodeTestImages::png(40, 40);

        $this->assertSame(0.10, (new QrCodeLogoDTO($png, QrCodeLogoDTO::MIN_WIDTH_RATIO))->widthRatio);
        $this->assertSame(0.25, (new QrCodeLogoDTO($png, QrCodeLogoDTO::MAX_WIDTH_RATIO))->widthRatio);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function ratiosOutOfBounds(): iterable
    {
        yield 'too small' => [0.09];
        yield 'too large' => [0.26];
        yield 'zero' => [0.0];
        yield 'whole code' => [1.0];
        yield 'not a number' => [NAN];
    }

    #[DataProvider('ratiosOutOfBounds')]
    public function test_it_rejects_ratios_out_of_bounds(float $ratio): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        new QrCodeLogoDTO(QrCodeTestImages::png(40, 40), $ratio);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedImages(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['not an image'];
        yield 'gif' => [QrCodeTestImages::gif(10, 10)];
        yield 'truncated png' => [substr(QrCodeTestImages::png(40, 40), 0, 20)];
        yield 'html with an svg' => ['<html><body><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/></body></html>'];
        yield 'svg without a size' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>'];
        yield 'svg with entities' => ['<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><text>&x;</text></svg>'];
    }

    #[DataProvider('unsupportedImages')]
    public function test_it_rejects_anything_but_png_jpeg_webp_or_a_usable_svg(string $contents): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        new QrCodeLogoDTO($contents);
    }

    public function test_it_reads_an_svg_logo_with_its_proportion(): void
    {
        $logo = new QrCodeLogoDTO(QrCodeTestImages::svg(200, 100), 0.15);

        $this->assertSame('image/svg+xml', $logo->mimeType);
        // A vector logo has no pixels: its longer side is reported as the largest raster logo
        $this->assertSame(QrCodeLogoDTO::MAX_DIMENSION, $logo->width);
        $this->assertSame(QrCodeLogoDTO::MAX_DIMENSION / 2, $logo->height);
        $this->assertSame(0.15, $logo->widthRatio);
    }

    public function test_it_reads_an_svg_logo_as_editors_save_it(): void
    {
        // PHP 8.5's getimagesize() knows SVG, with an XML declaration in front
        $logo = new QrCodeLogoDTO(
            '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n".'<!-- Created with an editor -->'."\n"
            .'<svg xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" width="210mm" height="105mm" viewBox="0 0 210 105" inkscape:version="1.3">'
            .'<metadata><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/></metadata><rect width="210" height="105" fill="#C81E1E"/></svg>',
        );

        $this->assertSame('image/svg+xml', $logo->mimeType);
        $this->assertSame(QrCodeLogoDTO::MAX_DIMENSION, $logo->width);
        $this->assertSame(QrCodeLogoDTO::MAX_DIMENSION / 2, $logo->height);
        $this->assertStringStartsWith('<svg', $logo->contents);
        $this->assertStringNotContainsString('metadata', $logo->contents);
        $this->assertStringNotContainsString('inkscape:version', $logo->contents);
    }

    public function test_a_tall_svg_logo_keeps_its_proportion(): void
    {
        $logo = new QrCodeLogoDTO(QrCodeTestImages::svg(1, 3));

        $this->assertSame(683, $logo->width);
        $this->assertSame(QrCodeLogoDTO::MAX_DIMENSION, $logo->height);
    }

    public function test_an_svg_logo_keeps_only_its_sanitized_markup(): void
    {
        $logo = new QrCodeLogoDTO(
            "\n  ".QrCodeTestImages::svg(10, 10, '<script>alert(1)</script><image href="https://evil.example.com/x.png" width="1" height="1"/>'),
        );

        $this->assertStringStartsWith('<svg', $logo->contents);
        $this->assertStringContainsString('#C81E1E', $logo->contents);
        $this->assertStringNotContainsString('script', $logo->contents);
        $this->assertStringNotContainsString('evil.example.com', $logo->contents);
    }

    public function test_raster_logos_keep_their_bytes(): void
    {
        $png = QrCodeTestImages::png(40, 40);

        $this->assertSame($png, (new QrCodeLogoDTO($png))->contents);
    }

    public function test_it_rejects_logos_over_the_byte_limit(): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        new QrCodeLogoDTO(QrCodeTestImages::png(40, 40).str_repeat("\0", QrCodeLogoDTO::MAX_BYTES));
    }

    public function test_it_rejects_logos_over_the_dimension_limit(): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        new QrCodeLogoDTO(QrCodeTestImages::png(QrCodeLogoDTO::MAX_DIMENSION + 1, 1));
    }
}
