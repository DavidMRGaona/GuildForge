<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

use DateTimeImmutable;

/**
 * A module request stored with an event proposal (core#581), as the author's list and the panel
 * show it. $active is false when no enabled module offers its key any more or it is unavailable:
 * then $fields is empty and $description is null, and $label is the key when no module registers it.
 */
final readonly class StoredProposalRequestDTO
{
    /**
     * @param  array<string, bool|int|string|null>  $requestedValues
     * @param  ?array<string, bool|int|string|null>  $approvedValues  null until a reviewer saves them
     * @param  list<ProposalRequestFieldDTO>  $fields
     * @param  ?string  $description  the module's line for the values in force (approved, else requested)
     */
    public function __construct(
        public string $id,
        public string $key,
        public string $label,
        public bool $active,
        public array $requestedValues,
        public ?array $approvedValues,
        public bool $apply,
        public ?DateTimeImmutable $appliedAt,
        public array $fields,
        public ?string $description,
    ) {}

    /**
     * @return array<string, bool|int|string|null>
     */
    public function valuesInForce(): array
    {
        return $this->approvedValues ?? $this->requestedValues;
    }
}
