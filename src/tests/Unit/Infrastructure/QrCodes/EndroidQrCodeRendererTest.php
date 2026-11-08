<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\QrCodes;

use App\Application\QrCodes\DTOs\QrCodeLogoDTO;
use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\Enums\QrCodeErrorCorrection;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeContentException;
use App\Domain\QrCodes\Exceptions\QrCodeRenderingException;
use App\Infrastructure\QrCodes\EndroidQrCodeRenderer;
use App\Infrastructure\QrCodes\QrCodeContentPolicy;
use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;
use Tests\Support\QrCodes\QrCodeTestImages;

final class EndroidQrCodeRendererTest extends TestCase
{
    private const string URL = 'https://guild.example.org/enlaces/mesa-3?ref=cartel';

    private EndroidQrCodeRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new EndroidQrCodeRenderer(new QrCodeContentPolicy('https://guild.example.org', allowsHttp: false));
    }

    public function test_it_renders_a_png_that_decodes_to_the_url(): void
    {
        $result = $this->renderer->render(self::URL, new QrCodeOptionsDTO(size: 400, margin: 20), QrCodeFormat::Png);

        $this->assertSame(QrCodeFormat::Png, $result->format);
        $this->assertSame('image/png', $result->mimeType());
        $this->assertSame('png', $result->extension());
        $this->assertSame([400, 400], array_slice((array) getimagesizefromstring($result->contents), 0, 2));
        $this->assertSame(self::URL, QrCodeTestImages::decode($result->contents));
    }

    public function test_the_png_uses_the_requested_colours(): void
    {
        $options = new QrCodeOptionsDTO(size: 256, margin: 32, foregroundColor: '#8B0000', backgroundColor: '#FFF8E7');

        $png = $this->renderer->render(self::URL, $options, QrCodeFormat::Png)->contents;

        // The margin is background; the top-left finder pattern starts with a dark module
        $this->assertSame([0xFF, 0xF8, 0xE7], QrCodeTestImages::pixel($png, 1, 1));
        $finder = $this->firstDarkPixelOnDiagonal($png, 256);
        $this->assertNotNull($finder);
        $this->assertSame([0x8B, 0x00, 0x00], QrCodeTestImages::pixel($png, $finder, $finder));
        $this->assertSame(self::URL, QrCodeTestImages::decode($png));
    }

    public function test_a_png_with_a_logo_still_decodes(): void
    {
        $options = new QrCodeOptionsDTO(
            size: 512,
            margin: 16,
            logo: new QrCodeLogoDTO(QrCodeTestImages::png(200, 200), QrCodeLogoDTO::MAX_WIDTH_RATIO),
        );

        $png = $this->renderer->render(self::URL, $options, QrCodeFormat::Png)->contents;

        $this->assertSame([512, 512], array_slice((array) getimagesizefromstring($png), 0, 2));
        // The logo sits in the centre
        $this->assertSame([200, 30, 30], QrCodeTestImages::pixel($png, 256, 256));
        $this->assertSame(self::URL, QrCodeTestImages::decode($png));
    }

    public function test_a_logo_renders_with_high_error_correction_whatever_was_asked(): void
    {
        $logo = new QrCodeLogoDTO(QrCodeTestImages::png(80, 80));

        $low = $this->renderer->render(self::URL, new QrCodeOptionsDTO(errorCorrection: QrCodeErrorCorrection::Low, logo: $logo), QrCodeFormat::Png);
        $high = $this->renderer->render(self::URL, new QrCodeOptionsDTO(errorCorrection: QrCodeErrorCorrection::High, logo: $logo), QrCodeFormat::Png);
        $withoutLogo = $this->renderer->render(self::URL, new QrCodeOptionsDTO(errorCorrection: QrCodeErrorCorrection::Low), QrCodeFormat::Png);

        $this->assertSame($high->contents, $low->contents);
        $this->assertNotSame($withoutLogo->contents, $low->contents);
    }

    public function test_it_renders_the_extreme_sizes(): void
    {
        $smallest = $this->renderer->render(self::URL, new QrCodeOptionsDTO(size: 128, margin: 0), QrCodeFormat::Png);
        $largest = $this->renderer->render(self::URL, new QrCodeOptionsDTO(size: 2048, margin: 512), QrCodeFormat::Png);

        $this->assertSame(self::URL, QrCodeTestImages::decode($smallest->contents));
        $this->assertSame(self::URL, QrCodeTestImages::decode($largest->contents));
    }

    public function test_decoding_the_largest_code_fits_in_a_test_process(): void
    {
        $largest = $this->renderer->render(self::URL, new QrCodeOptionsDTO(size: 2048, margin: 512), QrCodeFormat::Png);

        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_usage();

        $this->assertSame(self::URL, QrCodeTestImages::decode($largest->contents));

        // About 85 MB with the decoder's GD source; its Imagick source needs over 300 MB, which
        // ran a paratest worker out of its 512 MB once earlier tests had used some of them
        $this->assertLessThan(160 * 1024 * 1024, memory_get_peak_usage() - $before);
    }

    public function test_it_renders_an_svg_document(): void
    {
        $options = new QrCodeOptionsDTO(size: 300, margin: 12, foregroundColor: '#123456');

        $result = $this->renderer->render(self::URL, $options, QrCodeFormat::Svg);

        $this->assertSame('image/svg+xml', $result->mimeType());
        $this->assertSame('svg', $result->extension());
        $this->assertStringStartsWith('<svg', $result->contents);

        $svg = $this->parseSvg($result->contents);
        $this->assertSame('svg', $svg->localName);
        $this->assertSame('http://www.w3.org/2000/svg', $svg->namespaceURI);
        $this->assertSame('0 0 300 300', $svg->getAttribute('viewBox'));
        $this->assertStringContainsStringIgnoringCase('fill="#123456"', $result->contents);
        $this->assertSame(0, $svg->getElementsByTagName('image')->length);
        $this->assertSame(0, $svg->getElementsByTagName('script')->length);
    }

    public function test_an_svg_with_a_logo_embeds_it_over_a_background_patch(): void
    {
        $logoPng = QrCodeTestImages::png(100, 50);
        $options = new QrCodeOptionsDTO(
            size: 400,
            margin: 0,
            backgroundColor: '#FFF8E7',
            logo: new QrCodeLogoDTO($logoPng, 0.2),
        );

        $svg = $this->parseSvg($this->renderer->render(self::URL, $options, QrCodeFormat::Svg)->contents);

        $images = $svg->getElementsByTagName('image');
        $this->assertSame(1, $images->length);
        $image = $images->item(0);
        $this->assertInstanceOf(DOMElement::class, $image);
        $this->assertSame('data:image/png;base64,'.base64_encode($logoPng), $image->getAttribute('href'));
        // 20% of a 400 px code is 80 px wide; the logo keeps its 2:1 proportion
        $this->assertSame('80', $image->getAttribute('width'));
        $this->assertSame('40', $image->getAttribute('height'));
        $this->assertSame('160', $image->getAttribute('x'));
        $this->assertSame('180', $image->getAttribute('y'));

        $patch = $image->previousSibling;
        $this->assertInstanceOf(DOMElement::class, $patch);
        $this->assertSame('rect', $patch->localName);
        $this->assertSame('#FFF8E7', $patch->getAttribute('fill'));
        $this->assertSame('80', $patch->getAttribute('width'));
        $this->assertSame('40', $patch->getAttribute('height'));
    }

    public function test_it_checks_the_url_before_rendering(): void
    {
        $this->expectException(InvalidQrCodeContentException::class);

        $this->renderer->render('https://evil.example.com/', new QrCodeOptionsDTO, QrCodeFormat::Png);
    }

    public function test_content_that_does_not_fit_the_size_fails_with_a_rendering_exception(): void
    {
        $url = 'https://guild.example.org/'.str_repeat('a', 900);

        $this->expectException(QrCodeRenderingException::class);

        $this->renderer->render($url, new QrCodeOptionsDTO(size: 128, margin: 0, errorCorrection: QrCodeErrorCorrection::High), QrCodeFormat::Png);
    }

    public function test_it_leaves_no_temporary_logo_file_behind(): void
    {
        $before = glob(sys_get_temp_dir().'/guildforge-qr-logo-*') ?: [];

        $options = new QrCodeOptionsDTO(logo: new QrCodeLogoDTO(QrCodeTestImages::png(80, 80)));
        $this->renderer->render(self::URL, $options, QrCodeFormat::Png);

        $this->assertSame($before, glob(sys_get_temp_dir().'/guildforge-qr-logo-*') ?: []);
    }

    private function parseSvg(string $contents): DOMElement
    {
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($contents, LIBXML_NONET));
        $this->assertInstanceOf(DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }

    private function firstDarkPixelOnDiagonal(string $png, int $size): ?int
    {
        for ($i = 0; $i < $size; $i++) {
            [$red, $green, $blue] = QrCodeTestImages::pixel($png, $i, $i);
            if ($red + $green + $blue < 300) {
                return $i;
            }
        }

        return null;
    }
}
