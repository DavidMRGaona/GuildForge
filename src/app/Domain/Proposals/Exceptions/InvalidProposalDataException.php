<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Exceptions;

/**
 * What a proposal holds breaks a rule the forms also check (an empty title, an image outside the
 * type's folder…): the service never trusts the form.
 */
final class InvalidProposalDataException extends ProposalException
{
    public static function field(string $field): self
    {
        return new self('invalid_'.$field, sprintf('The proposal field [%s] is not valid.', $field));
    }
}
