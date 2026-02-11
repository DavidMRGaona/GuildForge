<?php

declare(strict_types=1);

namespace App\Domain\Mail\Enums;

enum EmailStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Complained = 'complained';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Enviado',
            self::Failed => 'Fallido',
            self::Bounced => 'Rebotado',
            self::Complained => 'Queja',
        };
    }
}
