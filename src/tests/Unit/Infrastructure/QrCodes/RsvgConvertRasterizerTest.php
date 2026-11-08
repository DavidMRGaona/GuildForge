<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\QrCodes;

use App\Infrastructure\QrCodes\RsvgConvertRasterizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\QrCodes\QrCodeTestImages;

final class RsvgConvertRasterizerTest extends TestCase
{
    public function test_it_draws_the_svg_at_the_requested_size(): void
    {
        $png = (new RsvgConvertRasterizer)->rasterize(QrCodeTestImages::svg(2, 1), 120, 60);

        $this->assertSame([120, 60, IMAGETYPE_PNG], array_slice((array) getimagesizefromstring($png), 0, 3));
        $this->assertSame([200, 30, 30], QrCodeTestImages::pixel($png, 60, 30));
    }

    public function test_it_reads_no_file_even_when_handed_an_unsanitized_svg(): void
    {
        // Second line of defence: the SVG goes in on stdin, with no base path to resolve against
        $secret = tempnam(sys_get_temp_dir(), 'qr-secret-');
        $this->assertIsString($secret);
        file_put_contents($secret.'.png', QrCodeTestImages::png(16, 16));

        try {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#00FF00"/>'
                .'<image width="10" height="10" href="file://'.$secret.'.png"/></svg>';

            $png = (new RsvgConvertRasterizer)->rasterize($svg, 40, 40);

            $this->assertSame([0, 255, 0], QrCodeTestImages::pixel($png, 20, 20));
        } finally {
            @unlink($secret);
            @unlink($secret.'.png');
        }
    }

    public function test_it_fails_when_the_svg_cannot_be_drawn(): void
    {
        $this->expectException(RuntimeException::class);

        (new RsvgConvertRasterizer)->rasterize('<svg xmlns="http://www.w3.org/2000/svg"', 40, 40);
    }

    public function test_it_fails_when_the_program_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rsvg-convert');

        (new RsvgConvertRasterizer('/nonexistent/rsvg-convert'))->rasterize(QrCodeTestImages::svg(1, 1), 40, 40);
    }

    public function test_it_rejects_sizes_beyond_a_logo(): void
    {
        $this->expectException(RuntimeException::class);

        (new RsvgConvertRasterizer)->rasterize(QrCodeTestImages::svg(1, 1), RsvgConvertRasterizer::MAX_DIMENSION + 1, 10);
    }
}
