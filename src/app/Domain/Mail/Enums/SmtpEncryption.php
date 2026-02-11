<?php

declare(strict_types=1);

namespace App\Domain\Mail\Enums;

enum SmtpEncryption: string
{
    case Tls = 'tls';
    case Ssl = 'ssl';
    case None = '';

    public function label(): string
    {
        return match ($this) {
            self::Tls => 'TLS',
            self::Ssl => 'SSL',
            self::None => 'Ninguna',
        };
    }
}
