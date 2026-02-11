<?php

declare(strict_types=1);

namespace App\Domain\Mail\Enums;

enum MailDriver: string
{
    case Smtp = 'smtp';
    case Mail = 'mail';
    case Ses = 'ses';
    case Resend = 'resend';
    case Log = 'log';
    case Array_ = 'array';

    public function label(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP',
            self::Mail => 'PHP Mail',
            self::Ses => 'Amazon SES',
            self::Resend => 'Resend',
            self::Log => 'Log (desarrollo)',
            self::Array_ => 'Array (pruebas)',
        };
    }

    public function requiresSmtpConfig(): bool
    {
        return $this === self::Smtp;
    }

    public function requiresSesConfig(): bool
    {
        return $this === self::Ses;
    }

    public function requiresResendConfig(): bool
    {
        return $this === self::Resend;
    }
}
