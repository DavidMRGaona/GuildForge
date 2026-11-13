<?php

declare(strict_types=1);

namespace App\Domain\Impersonation\Exceptions;

use App\Domain\Impersonation\Enums\ImpersonationRefusal;
use DomainException;

final class ImpersonationNotAllowedException extends DomainException
{
    public function __construct(
        public readonly ImpersonationRefusal $refusal,
    ) {
        parent::__construct('Impersonation not allowed: '.$refusal->value);
    }
}
