<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Exceptions;

/**
 * The person may not do this: proposals are off, the account is not active or verified, it lacks
 * the access level, the permission to review, or it is not the proposal's author.
 */
final class ProposalNotAllowedException extends ProposalException
{
    public static function because(string $reason): self
    {
        return new self($reason, 'Proposal action not allowed: '.$reason.'.');
    }
}
