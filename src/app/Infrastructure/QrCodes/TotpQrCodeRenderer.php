<?php

declare(strict_types=1);

namespace App\Infrastructure\QrCodes;

use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\DTOs\RenderedQrCodeDTO;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Domain\QrCodes\Exceptions\QrCodeRenderingException;
use Throwable;

/**
 * Authenticator URIs (otpauth://totp/…) as QR codes, for two-factor setup only. A class of its
 * own, apart from the renderer modules get, and its failures never carry the URI (it holds the
 * secret): neither in the message nor in a previous exception whose trace would hold it.
 *
 * @internal
 */
final readonly class TotpQrCodeRenderer implements TotpQrCodeRendererInterface
{
    public function __construct(
        private QrCodeContentPolicy $policy,
        private EndroidQrCodeDrawer $drawer = new EndroidQrCodeDrawer,
    ) {}

    public function renderTotpUri(#[\SensitiveParameter] string $uri, QrCodeOptionsDTO $options, QrCodeFormat $format): RenderedQrCodeDTO
    {
        $this->policy->assertTotpUriAllowed($uri);

        try {
            return $this->drawer->draw($uri, $options, $format);
        } catch (Throwable) {
            throw QrCodeRenderingException::withoutDetails();
        }
    }
}
