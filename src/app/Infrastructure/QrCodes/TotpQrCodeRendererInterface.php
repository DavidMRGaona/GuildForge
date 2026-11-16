<?php

declare(strict_types=1);

namespace App\Infrastructure\QrCodes;

use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\DTOs\RenderedQrCodeDTO;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeContentException;
use App\Domain\QrCodes\Exceptions\QrCodeRenderingException;

/**
 * The two-factor setup's own way to draw an authenticator URI (otpauth://totp/…) as a QR code.
 *
 * @internal Only the core's two-factor service uses it. It is not part of the public contract:
 *           modules draw codes through QrCodeRendererInterface, which only takes the site's URLs
 *           and is implemented by another class (EndroidQrCodeRenderer).
 */
interface TotpQrCodeRendererInterface
{
    /**
     * @throws InvalidQrCodeContentException when the content is not an authenticator URI
     * @throws QrCodeRenderingException when the image cannot be drawn, without details or a previous exception
     */
    public function renderTotpUri(#[\SensitiveParameter] string $uri, QrCodeOptionsDTO $options, QrCodeFormat $format): RenderedQrCodeDTO;
}
