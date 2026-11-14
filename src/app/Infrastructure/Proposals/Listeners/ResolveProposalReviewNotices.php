<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Listeners;

use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Domain\Proposals\Events\ProposalApproved;
use App\Domain\Proposals\Events\ProposalRejected;
use App\Domain\Proposals\Events\ProposalReturned;
use App\Domain\Proposals\Events\ProposalWithdrawn;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Once a proposal is no longer waiting for review (approved, returned, rejected or withdrawn by
 * its author), nobody is asked to review it any more: the «Propuesta para revisar» notices of
 * every reviewer are marked read (core#668). The events come after the commit; the author's own
 * notice of what became of it belongs to another category and stays unread.
 */
final readonly class ResolveProposalReviewNotices
{
    public const string SUBJECT_TYPE = 'core.proposal';

    public function __construct(private SiteNotificationServiceInterface $notices) {}

    public function handle(ProposalApproved|ProposalReturned|ProposalRejected|ProposalWithdrawn $event): void
    {
        try {
            $this->notices->resolveSubject(CoreMailCategories::PROPOSALS_REVIEW, self::SUBJECT_TYPE, $event->id);
        } catch (Throwable $exception) {
            // The proposal is already resolved: a stale notice must not turn that into an error
            Log::error('Could not mark the review notices of a resolved proposal as read.', [
                'proposal_id' => $event->id,
                'exception' => $exception,
            ]);
        }
    }
}
