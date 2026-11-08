<?php

declare(strict_types=1);

namespace App\Domain\QrCodes\Exceptions;

use DomainException;

/**
 * The options asked for a QR code would give an image out of bounds or hard to scan.
 */
final class InvalidQrCodeOptionsException extends DomainException
{
    public static function because(string $reason): self
    {
        return new self('Invalid QR code options: '.$reason);
    }
}
