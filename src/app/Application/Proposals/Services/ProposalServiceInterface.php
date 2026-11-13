<?php

declare(strict_types=1);

namespace App\Application\Proposals\Services;

use App\Application\Proposals\DTOs\ArticleProposalDataDTO;
use App\Application\Proposals\DTOs\EventProposalDataDTO;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\InvalidProposalDataException;
use App\Domain\Proposals\Exceptions\InvalidProposalTransitionException;
use App\Domain\Proposals\Exceptions\ProposalLimitReachedException;
use App\Domain\Proposals\Exceptions\ProposalNotAllowedException;
use App\Domain\Proposals\Exceptions\ProposalNotFoundException;

/**
 * The review flow of events and articles proposed from the web (core#570), for the web and the
 * panel alike. Every operation checks who may do it on its own: never trust the caller's buttons.
 *
 * - Authors (create, update, submit) need ProposalEligibilityServiceInterface's approval; withdraw
 *   and delete only need to be the author of an account still active, so whoever lost access
 *   can still take back or remove what is theirs.
 * - Caps: open drafts (create), proposals in review and submissions per hour (submit), per person
 *   across both types.
 * - Reviewers (approve, return, reject) need the type's review permission.
 * - Submitting with the type's publish-directly permission approves at once; if that approval
 *   fails, the proposal stays in review.
 * - Domain events (ProposalSubmitted, ProposalApproved, ProposalReturned, ProposalRejected,
 *   ProposalWithdrawn) are dispatched after the commit.
 *
 * @throws ProposalNotAllowedException|ProposalLimitReachedException|ProposalNotFoundException|InvalidProposalTransitionException|InvalidProposalDataException
 */
interface ProposalServiceInterface
{
    /** @return string the new event's id */
    public function createEvent(string $userId, EventProposalDataDTO $data): string;

    public function updateEvent(string $userId, string $eventId, EventProposalDataDTO $data): void;

    /** @return string the new article's id */
    public function createArticle(string $userId, ArticleProposalDataDTO $data): string;

    public function updateArticle(string $userId, string $articleId, ArticleProposalDataDTO $data): void;

    /**
     * @return ProposalStatus PendingReview, or Approved when it was published directly
     */
    public function submit(string $userId, ProposalType $type, string $id): ProposalStatus;

    public function withdraw(string $userId, ProposalType $type, string $id): void;

    public function delete(string $userId, ProposalType $type, string $id): void;

    /**
     * Publishes the proposal (and runs every ProposalApprovalStepInterface) in one transaction.
     */
    public function approve(string $reviewerId, ProposalType $type, string $id): void;

    /** Back to a draft its author may correct and submit again */
    public function returnToDraft(string $reviewerId, ProposalType $type, string $id, string $reason): void;

    public function reject(string $reviewerId, ProposalType $type, string $id, string $reason): void;

    /**
     * Deletes every unapproved proposal (drafts, in review and rejected) of an account being
     * anonymized or deleted. What was published stays.
     *
     * @return int how many were deleted
     */
    public function deleteUnpublishedOf(string $userId): int;

    /**
     * Deletes the proposals rejected longer ago than the settings keep them.
     *
     * @return int how many were deleted
     */
    public function purgeRejected(): int;
}
