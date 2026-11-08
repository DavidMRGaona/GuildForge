<?php

declare(strict_types=1);

namespace Tests\Unit\Application\QrCodes\DTOs;

use App\Application\QrCodes\DTOs\QrCodeLogoDTO;
use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\Enums\QrCodeErrorCorrection;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeOptionsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\QrCodes\QrCodeTestImages;

final class QrCodeOptionsDTOTest extends TestCase
{
    public function test_defaults_are_a_readable_black_on_white_code(): void
    {
        $options = new QrCodeOptionsDTO;

        $this->assertSame(512, $options->size);
        $this->assertSame(16, $options->margin);
        $this->assertSame(QrCodeErrorCorrection::Medium, $options->errorCorrection);
        $this->assertSame('#000000', $options->foreground()->hex());
        $this->assertSame('#FFFFFF', $options->background()->hex());
        $this->assertNull($options->logo);
        $this->assertSame(QrCodeErrorCorrection::Medium, $options->effectiveErrorCorrection());
    }

    public function test_it_accepts_the_bounds(): void
    {
        $smallest = new QrCodeOptionsDTO(size: QrCodeOptionsDTO::MIN_SIZE, margin: 0);
        $largest = new QrCodeOptionsDTO(size: QrCodeOptionsDTO::MAX_SIZE, margin: intdiv(QrCodeOptionsDTO::MAX_SIZE, 4));

        $this->assertSame(128, $smallest->size);
        $this->assertSame(2048, $largest->size);
        $this->assertSame(512, $largest->margin);
    }

    public function test_a_logo_raises_the_error_correction_to_high(): void
    {
        $options = new QrCodeOptionsDTO(
            errorCorrection: QrCodeErrorCorrection::Low,
            logo: new QrCodeLogoDTO(QrCodeTestImages::png(64, 64)),
        );

        $this->assertSame(QrCodeErrorCorrection::Low, $options->errorCorrection);
        $this->assertSame(QrCodeErrorCorrection::High, $options->effectiveErrorCorrection());
    }

    public function test_it_accepts_brand_colours_with_enough_contrast(): void
    {
        $options = new QrCodeOptionsDTO(foregroundColor: '#8b0000', backgroundColor: '#fff8e7');

        $this->assertSame('#8B0000', $options->foreground()->hex());
        $this->assertSame('#FFF8E7', $options->background()->hex());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function sizesOutOfBounds(): iterable
    {
        yield 'too small' => [QrCodeOptionsDTO::MIN_SIZE - 1];
        yield 'too large' => [QrCodeOptionsDTO::MAX_SIZE + 1];
        yield 'zero' => [0];
        yield 'negative' => [-512];
    }

    #[DataProvider('sizesOutOfBounds')]
    public function test_it_rejects_sizes_out_of_bounds(int $size): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        new QrCodeOptionsDTO(size: $size, margin: 0);
    }

    public function test_it_rejects_a_negative_margin(): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        new QrCodeOptionsDTO(margin: -1);
    }

    public function test_it_rejects_a_margin_over_a_quarter_of_the_size(): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        new QrCodeOptionsDTO(size: 512, margin: 129);
    }

    public function test_it_rejects_invalid_colours(): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        new QrCodeOptionsDTO(foregroundColor: 'red');
    }

    public function test_it_rejects_low_contrast(): void
    {
        // #808080 on white is about 3.95:1
        $this->expectException(InvalidQrCodeOptionsException::class);
        $this->expectExceptionMessageMatches('/contrast/i');

        new QrCodeOptionsDTO(foregroundColor: '#808080', backgroundColor: '#FFFFFF');
    }

    public function test_it_rejects_a_light_code_on_a_dark_background(): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);
        $this->expectExceptionMessageMatches('/darker/i');

        new QrCodeOptionsDTO(foregroundColor: '#FFFFFF', backgroundColor: '#000000');
    }

    public function test_it_rejects_the_same_colour_twice(): void
    {
        $this->expectException(InvalidQrCodeOptionsException::class);

        new QrCodeOptionsDTO(foregroundColor: '#000000', backgroundColor: '#000000');
    }
}
