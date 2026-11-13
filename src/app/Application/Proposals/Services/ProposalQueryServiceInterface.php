<?php

declare(strict_types=1);

namespace App\Application\Proposals\Services;

use App\Application\Proposals\DTOs\OwnProposalDTO;
use App\Application\Proposals\DTOs\ProposalLimitsDTO;
use App\Domain\Proposals\Enums\ProposalType;

/**
 * Reads of the proposals for the web (core#579): what a person sees in «Mis propuestas», what
 * they edit and who proposed a published event. Writes go through ProposalServiceInterface.
 */
interface ProposalQueryServiceInterface
{
    /**
     * Every proposal the person made (drafts, in review, rejected and approved), newest first.
     *
     * @return list<OwnProposalDTO>
     */
    public function ownedBy(string $userId): array;

    /**
     * One proposal of the person, or null when it does not exist or is someone else's: the caller
     * answers 404 for both, so nobody learns which ids exist.
     */
    public function findOwned(string $userId, ProposalType $type, string $id): ?OwnProposalDTO;

    public function limitsOf(string $userId): ProposalLimitsDTO;

    /**
     * Name to show as «Propuesto por…» on a published event: only when the setting is on, the
     * event was proposed and approved and its proposer still has a name to show. Null otherwise.
     */
    public function proposerNameOfEvent(string $eventId): ?string;
}
