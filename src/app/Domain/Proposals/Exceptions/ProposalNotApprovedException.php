<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Exceptions;

/**
 * Only approving publishes a proposal: neither the panel toggle nor code may publish one that is
 * not approved.
 */
final class ProposalNotApprovedException extends ProposalException
{
    public static function cannotPublish(): self
    {
        return new self('not_approved', 'An unapproved proposal cannot be published.');
    }
}
