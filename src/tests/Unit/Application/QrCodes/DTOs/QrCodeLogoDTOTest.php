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
    public static function notRasterImages(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['not an image'];
        yield 'svg' => ['<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>'];
        yield 'gif' => [QrCodeTestImages::gif(10, 10)];
        yield 'truncated png' => [substr(QrCodeTestImages::png(40, 40), 0, 20)];
    }

    #[DataProvider('notRasterImages')]
    public function test_it_rejects_anything_but_png_jpeg_or_webp(string $contents): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        new QrCodeLogoDTO($contents);
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
