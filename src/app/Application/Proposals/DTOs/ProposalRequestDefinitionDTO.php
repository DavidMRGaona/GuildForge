<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

/**
 * A module request offered on the event proposal form right now (core#581): what the core draws.
 */
final readonly class ProposalRequestDefinitionDTO
{
    /**
     * @param  list<ProposalRequestFieldDTO>  $fields
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $question,
        public array $fields,
    ) {}
}
