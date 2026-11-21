<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Observers;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Activity\Sources\ProposalActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the activity contract (core#777) when a proposed event or article starts or stops counting:
 * approved (by a reviewer or by publishing directly), unpublished or published again in the panel,
 * its proposer changed, or deleted. The status lives on the content, so watching its row covers
 * every path. A row that is not a proposal, and a proposal that is not approved,
 * publish nothing. Never fails the save.
 */
final readonly class ProposalActivityObserver
{
    private const array WATCHED = ['proposal_status', 'is_published', 'proposed_by', 'reviewed_at'];

    /** The container: an observer lives as long as the process, the publisher is scoped */
    public function __construct(
        private Container $container,
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
        if ($content->proposalStatus() instanceof ProposalStatus && $content->wasChanged(self::WATCHED)) {
            $this->publish($content);
        }
    }

    public function deleted(EventModel|ArticleModel $content): void
    {
        if ($content->proposalStatus() === ProposalStatus::Approved) {
            $this->publish($content);
        }
    }

    private function publish(EventModel|ArticleModel $content): void
    {
        $type = $content instanceof EventModel ? ProposalType::Event : ProposalType::Article;

        try {
            $this->container->make(ActivityPublisherInterface::class)->recorded(ProposalActivitySource::TYPE, ProposalActivitySource::subjectIdOf($type, (string) $content->getKey()));
        } catch (Throwable $exception) {
            Log::warning('The activity of a proposal could not be published.', [
                'type' => $type->value,
                'id' => (string) $content->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }
}
