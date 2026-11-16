<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\QrCodes;

use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\Enums\QrCodeErrorCorrection;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeContentException;
use App\Domain\QrCodes\Exceptions\QrCodeRenderingException;
use App\Infrastructure\QrCodes\QrCodeContentPolicy;
use App\Infrastructure\QrCodes\TotpQrCodeRenderer;
use PHPUnit\Framework\TestCase;
use Tests\Support\QrCodes\QrCodeTestImages;

final class TotpQrCodeRendererTest extends TestCase
{
    private const string SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    private const string URI = 'otpauth://totp/Example%20site:ana%40example.org?secret='.self::SECRET.'&issuer=Example%20site';

    private TotpQrCodeRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new TotpQrCodeRenderer(new QrCodeContentPolicy('https://guild.example.org', allowsHttp: false));
    }

    public function test_it_renders_an_authenticator_uri(): void
    {
        $result = $this->renderer->renderTotpUri(self::URI, new QrCodeOptionsDTO(size: 400, margin: 20), QrCodeFormat::Png);

        $this->assertSame(self::URI, QrCodeTestImages::decode($result->contents));
        $this->assertStringStartsWith('<svg', $this->renderer->renderTotpUri(self::URI, new QrCodeOptionsDTO, QrCodeFormat::Svg)->contents);
    }

    public function test_it_draws_nothing_but_authenticator_uris(): void
    {
        $this->expectException(InvalidQrCodeContentException::class);

        $this->renderer->renderTotpUri('https://guild.example.org/enlaces', new QrCodeOptionsDTO, QrCodeFormat::Png);
    }

    public function test_a_failure_carries_neither_the_uri_nor_a_previous_exception(): void
    {
        // A URI the policy takes but too long for the smallest code with the most correction
        $uri = 'otpauth://totp/'.str_repeat('a', 900).'?secret='.self::SECRET;

        try {
            $this->renderer->renderTotpUri($uri, new QrCodeOptionsDTO(size: 128, margin: 0, errorCorrection: QrCodeErrorCorrection::High), QrCodeFormat::Png);
            $this->fail('Expected a rendering exception');
        } catch (QrCodeRenderingException $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString(self::SECRET, $exception->getMessage());
            $this->assertStringNotContainsString(self::SECRET, $exception->getTraceAsString());
        }
    }
}
