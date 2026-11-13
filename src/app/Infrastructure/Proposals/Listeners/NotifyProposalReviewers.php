<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Listeners;

use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Events\ProposalSubmitted;
use App\Infrastructure\Authorization\Services\PermissionHolderFinder;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\ProposalToReviewNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A proposal waits for review: tells everyone who holds the type's review permission, never its
 * author. A proposal published directly dispatches ProposalApproved instead, so it never gets here.
 * A notice that cannot be queued is logged and skipped: submitting already happened.
 */
final readonly class NotifyProposalReviewers
{
    public function __construct(
        private PermissionHolderFinder $holders,
        private EventProposalRequestServiceInterface $requests,
    ) {}

    public function handle(ProposalSubmitted $event): void
    {
        $proposal = match ($event->type) {
            ProposalType::Event => EventModel::query()->includingUnsubmittedProposals()->find($event->id),
            ProposalType::Article => ArticleModel::query()->includingUnsubmittedProposals()->find($event->id),
        };
        $author = UserModel::query()->find($event->proposerId);

        if ($proposal === null || ! $author instanceof UserModel) {
            return;
        }

        $authorName = $author->display_name ?? $author->name;
        $requestLines = $event->type === ProposalType::Event ? $this->requestLines($event->id) : [];

        $this->holders
            ->usersWhoCan($event->type->reviewPermission())
            ->reject(fn (UserModel $reviewer): bool => (string) $reviewer->getKey() === $event->proposerId)
            ->each(function (UserModel $reviewer) use ($event, $proposal, $authorName, $requestLines): void {
                try {
                    $reviewer->notify(new ProposalToReviewNotification(
                        $event->type,
                        $event->id,
                        $proposal->title,
                        $authorName,
                        $event->proposerId,
                        $requestLines,
                    ));
                } catch (Throwable $exception) {
                    Log::error('Could not queue the proposal to review notice.', [
                        'user_id' => $reviewer->getKey(),
                        'proposal_id' => $event->id,
                        'exception' => $exception,
                    ]);
                }
            });
    }

    /**
     * What the author asked the modules for, one line each (core#581). A request whose module
     * says nothing about it is left out.
     *
     * @return list<string>
     */
    private function requestLines(string $eventId): array
    {
        $lines = [];

        foreach ($this->requests->forEvent($eventId) as $request) {
            if ($request->description !== null) {
                $lines[] = (string) __('proposals.requests.notice_line', ['label' => $request->label, 'description' => $request->description]);
            }
        }

        return $lines;
    }
}
