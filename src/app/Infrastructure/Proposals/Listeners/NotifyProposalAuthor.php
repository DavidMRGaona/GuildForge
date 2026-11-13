<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Listeners;

use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Events\ProposalApproved;
use App\Domain\Proposals\Events\ProposalRejected;
use App\Domain\Proposals\Events\ProposalReturned;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\ProposalResolvedNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the author what became of their proposal. Nobody is told about what they did
 * themselves: a proposal published directly by submitting it (`automatic`) or reviewed by its own
 * author says nothing.
 */
final readonly class NotifyProposalAuthor
{
    public function approved(ProposalApproved $event): void
    {
        if ($event->automatic) {
            return;
        }

        $this->notify($event->type, $event->id, $event->proposerId, $event->reviewerId, 'approved', null);
    }

    public function returned(ProposalReturned $event): void
    {
        $this->notify($event->type, $event->id, $event->proposerId, $event->reviewerId, 'returned', $event->reason);
    }

    public function rejected(ProposalRejected $event): void
    {
        $this->notify($event->type, $event->id, $event->proposerId, $event->reviewerId, 'rejected', $event->reason);
    }

    /**
     * @param  'approved'|'returned'|'rejected'  $outcome
     */
    private function notify(ProposalType $type, string $id, ?string $proposerId, ?string $reviewerId, string $outcome, ?string $reason): void
    {
        if ($proposerId === null || $proposerId === $reviewerId) {
            return;
        }

        $author = UserModel::query()->whereNull('anonymized_at')->find($proposerId);
        $proposal = match ($type) {
            ProposalType::Event => EventModel::query()->includingUnsubmittedProposals()->find($id),
            ProposalType::Article => ArticleModel::query()->includingUnsubmittedProposals()->find($id),
        };

        if (! $author instanceof UserModel || $proposal === null) {
            return;
        }

        try {
            $author->notify(new ProposalResolvedNotification($type, $id, $proposal->title, $outcome, $reason));
        } catch (Throwable $exception) {
            Log::error('Could not queue the proposal status notice.', [
                'user_id' => $proposerId,
                'proposal_id' => $id,
                'exception' => $exception,
            ]);
        }
    }
}
