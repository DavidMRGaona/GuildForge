<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

use App\Domain\Proposals\Enums\ProposalAccessLevel;

/**
 * Proposal settings of one type of content (events or articles).
 */
final readonly class ProposalTypeSettingsDTO
{
    /**
     * @param  ?ProposalAccessLevel  $accessLevel  null when the stored level is unreadable: nobody may propose
     * @param  list<string>  $roles  role names allowed with the Roles level
     * @param  bool  $showProposer  show «Propuesto por» on the published page (events only)
     */
    public function __construct(
        public bool $enabled = false,
        public ?ProposalAccessLevel $accessLevel = ProposalAccessLevel::Registered,
        public array $roles = [],
        public bool $showProposer = false,
    ) {}
}
