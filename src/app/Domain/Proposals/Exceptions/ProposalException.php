<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Exceptions;

use DomainException;

/**
 * Base of every refusal of the proposals flow. $reason is a stable code the presentation layer
 * maps to a message and a status, never shown as is.
 */
abstract class ProposalException extends DomainException
{
    final protected function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
