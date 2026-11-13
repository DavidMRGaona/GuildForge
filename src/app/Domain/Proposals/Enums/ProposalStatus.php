<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Enums;

/**
 * Review of an event or article proposed from the web: the author prepares a draft and sends it
 * to review; approving it publishes it, returning it sends it back to a draft with the reason and
 * rejecting it closes it with the reason. Content created in the panel has no status (null).
 *
 * The single rule behind the service, the policies and the panel: what the author may do, whether
 * the panel's «Publicado» toggle may publish and whether a proposal is still unapproved.
 */
enum ProposalStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * What the author may do in this status: edit, submit and delete a draft; only withdraw one
     * awaiting review; only delete a rejected one; nothing once approved.
     *
     * @return list<ProposalAuthorAction>
     */
    public function authorActions(): array
    {
        return match ($this) {
            self::Draft => [ProposalAuthorAction::Edit, ProposalAuthorAction::Submit, ProposalAuthorAction::Delete],
            self::PendingReview => [ProposalAuthorAction::Withdraw],
            self::Approved => [],
            self::Rejected => [ProposalAuthorAction::Delete],
        };
    }

    /**
     * Whether the author may take this action. Content created in the panel (no status) is never
     * in the author's hands.
     */
    public static function allowsAuthor(?self $status, ProposalAuthorAction $action): bool
    {
        return $status instanceof self && in_array($action, $status->authorActions(), true);
    }

    /**
     * Whether the panel may publish with its «Publicado» toggle: only content created there (no
     * status) and approved proposals, which it may also unpublish and publish again. Any other
     * proposal is published only by approving it.
     */
    public static function allowsPanelPublishing(?self $status): bool
    {
        return ! $status instanceof self || $status === self::Approved;
    }

    /**
     * Whether this is a proposal not approved yet, which must never be published.
     */
    public static function isUnapproved(?self $status): bool
    {
        return $status instanceof self && $status !== self::Approved;
    }

    /**
     * Stored values of every unapproved status, for queries.
     *
     * @return list<string>
     */
    public static function unapprovedValues(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), self::isUnapproved(...)),
        ));
    }
}
