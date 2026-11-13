<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Exceptions;

use DomainException;

/**
 * Thrown by a module request's apply() when it cannot create what was asked for (the space is no
 * longer free, the event already has its configuration…). Its message is shown to whoever
 * approves, so it must be translated and readable; any other exception is reported and shown as a
 * generic failure. Either way the approval is rolled back.
 */
final class ProposalRequestRefusedException extends DomainException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
