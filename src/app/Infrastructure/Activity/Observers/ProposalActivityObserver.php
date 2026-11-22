<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Observers;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Activity\Sources\ProposalActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the activity contract (core#777) when a proposed event or article starts or stops counting:
 * approved (by a reviewer or by publishing directly), unpublished or published again in the panel
 * while approved, its proposer or, for an article, the byline changed (the proposer counts only
 * while shown), or deleted. The status lives on the content, so watching its row covers every
 * path. Only an approved proposal counts, so a row publishes only when it is, or was, approved:
 * submitting, returning or rejecting one publish nothing. A failure never fails the save.
 */
final readonly class ProposalActivityObserver
{
    private const array WATCHED = ['proposal_status', 'is_published', 'proposed_by', 'reviewed_at', 'author_id'];

    /** Laravel builds an observer for every event it handles, so it gets the publisher of the request or job at hand */
    public function __construct(
        private ActivityPublisherInterface $activity,
    ) {}

    /** Created already approved (a seeder, an import of old content); anything else is a draft or in review */
    public function created(EventModel|ArticleModel $content): void
    {
        if ($content->proposalStatus() === ProposalStatus::Approved) {
            $this->publish($content);
        }
    }

    public function updated(EventModel|ArticleModel $content): void
    {
        if (! $content->wasChanged(self::WATCHED)) {
            return;
        }

        $previous = $this->statusOf($content->getPrevious()['proposal_status'] ?? $content->getRawOriginal('proposal_status'));

        // Becomes approved, leaves approved, or changes while approved (published or not, another proposer)
        if ($content->proposalStatus() === ProposalStatus::Approved || $previous === ProposalStatus::Approved) {
            $this->publish($content);
        }
    }

    public function deleted(EventModel|ArticleModel $content): void
    {
        if ($content->proposalStatus() === ProposalStatus::Approved) {
            $this->publish($content);
        }
    }

    private function statusOf(mixed $value): ?ProposalStatus
    {
        return $value instanceof ProposalStatus ? $value : (is_string($value) ? ProposalStatus::tryFrom($value) : null);
    }

    private function publish(EventModel|ArticleModel $content): void
    {
        $type = $content instanceof EventModel ? ProposalType::Event : ProposalType::Article;

        try {
            $this->activity->recorded(ProposalActivitySource::TYPE, ProposalActivitySource::subjectIdOf($type, (string) $content->getKey()));
        } catch (Throwable $exception) {
            Log::warning('The activity of a proposal could not be published.', [
                'type' => $type->value,
                'id' => (string) $content->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }
}
