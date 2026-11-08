<?php

declare(strict_types=1);

namespace App\Domain\QrCodes\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Valid content and options that still could not be drawn, e.g. a URL too long for the size.
 */
final class QrCodeRenderingException extends RuntimeException
{
    public static function from(Throwable $previous): self
    {
        return new self('The QR code could not be rendered: '.$previous->getMessage(), previous: $previous);
    }
}
