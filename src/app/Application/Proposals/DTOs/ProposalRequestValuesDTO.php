<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

/**
 * What ProposalRequestValueRule made of the values sent for one request: every field with its
 * clean value (null when empty or hidden) and, per field, why it was refused.
 */
final readonly class ProposalRequestValuesDTO
{
    /**
     * @param  array<string, bool|int|string|null>  $values
     * @param  array<string, string>  $errors  field name => code: required, boolean, integer, min, max, option, text, max_length
     */
    public function __construct(
        public array $values,
        public array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
