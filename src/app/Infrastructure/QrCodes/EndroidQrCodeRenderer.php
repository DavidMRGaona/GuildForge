<?php

declare(strict_types=1);

namespace App\Infrastructure\QrCodes;

use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\DTOs\RenderedQrCodeDTO;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\QrCodes\Services\QrCodeRendererInterface;

/**
 * QR codes for modules: only URLs of the site, checked by QrCodeContentPolicy::assertAllowed(),
 * drawn by EndroidQrCodeDrawer. Authenticator URIs have a renderer of their own
 * (TotpQrCodeRenderer), so nothing reached through this contract can draw them.
 */
final readonly class EndroidQrCodeRenderer implements QrCodeRendererInterface
{
    private EndroidQrCodeDrawer $drawer;

    public function __construct(
        private QrCodeContentPolicy $policy,
        RsvgConvertRasterizer $rasterizer = new RsvgConvertRasterizer,
    ) {
        $this->drawer = new EndroidQrCodeDrawer($rasterizer);
    }

    public function render(string $url, QrCodeOptionsDTO $options, QrCodeFormat $format): RenderedQrCodeDTO
    {
        $this->policy->assertAllowed($url);

        return $this->drawer->draw($url, $options, $format);
    }
}
