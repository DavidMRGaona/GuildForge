<?php

declare(strict_types=1);

namespace App\Application\Proposals\Services;

use App\Application\Proposals\DTOs\ProposalRequestDefinitionDTO;
use App\Application\Proposals\DTOs\ProposalRequestSubmissionDTO;
use App\Application\Proposals\DTOs\StoredProposalRequestDTO;
use App\Domain\Proposals\Exceptions\InvalidProposalDataException;
use App\Domain\Proposals\Exceptions\InvalidProposalTransitionException;
use App\Domain\Proposals\Exceptions\ProposalNotAllowedException;
use App\Domain\Proposals\Exceptions\ProposalNotFoundException;
use App\Domain\Proposals\Exceptions\ProposalRequestNotAppliedException;

/**
 * The module requests of event proposals (core#581): what the form offers, the values people ask
 * for, what the reviewer approves and applying them on approval. Every module call is guarded:
 * one that throws is left out (offer, description) or stops the approval (apply), never
 * anything in between.
 *
 * A submission is `key => ['wanted' => bool, 'values' => array<field, mixed>]`, as the web form sends it.
 */
interface EventProposalRequestServiceInterface
{
    /**
     * The requests offered now: registered by an enabled module and available.
     *
     * @return list<ProposalRequestDefinitionDTO>
     */
    public function definitions(): array;

    /**
     * Checks a submission against the requests offered now (ProposalRequestValueRule).
     *
     * @param  array<array-key, mixed>  $submitted
     */
    public function check(array $submitted): ProposalRequestSubmissionDTO;

    /**
     * Stores what the author asks for, for the requests offered now: a request asked for is
     * written with its values (any approved values and the apply flag start over), one not asked
     * for is removed. Requests not offered any more stay as they were. Call it inside the
     * transaction that saves the proposal, once the author may edit it.
     *
     * @param  array<array-key, mixed>  $submitted
     *
     * @throws InvalidProposalDataException invalid_requests when check() refuses something
     */
    public function saveRequested(string $eventId, array $submitted): void;

    /**
     * The stored requests of an event, ordered as the registry orders them (inactive ones last).
     *
     * @return list<StoredProposalRequestDTO>
     */
    public function forEvent(string $eventId): array;

    /**
     * The reviewer's decisions, `key => ['apply' => bool, 'values' => array<field, mixed>]`: whether
     * to apply each stored request and, for active ones, the values to apply. Only for whoever
     * may review events, while the proposal awaits review. Unknown keys are ignored; the values
     * of an inactive request cannot change (it can only be unticked).
     *
     * @param  array<array-key, mixed>  $decisions
     *
     * @throws ProposalNotAllowedException|ProposalNotFoundException|InvalidProposalTransitionException
     * @throws InvalidProposalDataException invalid_requests.<key>.<field> on the first refused value
     */
    public function review(string $reviewerId, string $eventId, array $decisions): void;

    /**
     * Applies every ticked request not applied yet, with the approved values (the requested ones
     * when $directly or none were approved), and records when. Meant for the approval
     * transaction: the first one that cannot be applied throws and nothing else should be kept.
     *
     * @throws ProposalRequestNotAppliedException
     */
    public function applyTicked(string $eventId, bool $directly): void;
}
