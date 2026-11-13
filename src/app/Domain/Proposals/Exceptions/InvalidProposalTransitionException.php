<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Exceptions;

use App\Domain\Proposals\Enums\ProposalStatus;

/**
 * The proposal is not in a status that allows the action (editing one awaiting review, approving
 * a draft…).
 */
final class InvalidProposalTransitionException extends ProposalException
{
    public static function from(ProposalStatus $status, string $action): self
    {
        return new self('invalid_status', sprintf('A proposal in status [%s] cannot be %s.', $status->value, $action));
    }

    public static function reasonRequired(): self
    {
        return new self('reason_required', 'Returning or rejecting a proposal needs a reason.');
    }
}
