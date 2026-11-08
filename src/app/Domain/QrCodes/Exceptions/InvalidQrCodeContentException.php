<?php

declare(strict_types=1);

namespace App\Domain\QrCodes\Exceptions;

use DomainException;

/**
 * A QR code may only carry a URL of the site itself (https; http only where allowed).
 */
final class InvalidQrCodeContentException extends DomainException
{
    public static function because(string $reason): self
    {
        return new self('Invalid QR code content: '.$reason);
    }
}
