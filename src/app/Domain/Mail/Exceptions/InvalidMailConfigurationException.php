<?php

declare(strict_types=1);

namespace App\Domain\Mail\Exceptions;

use DomainException;

final class InvalidMailConfigurationException extends DomainException
{
    public static function missingSmtpHost(): self
    {
        return new self('SMTP host is required when using the SMTP driver.');
    }

    public static function missingFromAddress(): self
    {
        return new self('From address is required for sending emails.');
    }

    public static function missingSesCredentials(): self
    {
        return new self('AWS access key and secret are required when using the SES driver.');
    }
}
