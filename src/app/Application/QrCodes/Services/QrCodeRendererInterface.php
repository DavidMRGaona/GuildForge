<?php

declare(strict_types=1);

namespace App\Application\QrCodes\Services;

use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\DTOs\RenderedQrCodeDTO;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeContentException;
use App\Domain\QrCodes\Exceptions\QrCodeRenderingException;

/**
 * Draws QR codes for modules (link pages, tickets, sign-up links...), which have no
 * Composer dependencies of their own. Options are validated by QrCodeOptionsDTO.
 */
interface QrCodeRendererInterface
{
    /**
     * @param  string  $url  An https URL of this site (same host and port as app.url); http only in local and testing
     *
     * @throws InvalidQrCodeContentException when the URL is not one of the site
     * @throws QrCodeRenderingException when the URL does not fit the size or the image cannot be drawn
     */
    public function render(string $url, QrCodeOptionsDTO $options, QrCodeFormat $format): RenderedQrCodeDTO;
}
