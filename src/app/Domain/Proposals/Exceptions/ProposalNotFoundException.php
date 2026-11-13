<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Exceptions;

/**
 * No proposal of that type with that id (content created in the panel is not a proposal).
 */
final class ProposalNotFoundException extends ProposalException
{
    public static function withId(string $id): self
    {
        return new self('not_found', sprintf('Proposal [%s] not found.', $id));
    }
}
