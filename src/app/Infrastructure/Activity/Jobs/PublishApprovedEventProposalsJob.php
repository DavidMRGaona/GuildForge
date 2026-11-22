<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Jobs;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Activity\Sources\ProposalActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Publishes every approved event proposal again (core#777). Event proposals count only while the
 * site shows who proposed them, so switching «Mostrar quién propone» on or off changes what
 * `core.proposal` reports for all of them at once: the consumer reads each one again and keeps or
 * drops its facts. Queued, and walked in batches by id, so a site with many proposals never holds
 * them all.
 */
final class PublishApprovedEventProposalsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $chunk = 500,
    ) {}

    public function handle(ActivityPublisherInterface $activity): void
    {
        EventModel::query()
            ->where('proposal_status', ProposalStatus::Approved->value)
            ->whereNotNull('proposed_by')
            ->chunkById(max(1, $this->chunk), static function ($events) use ($activity): void {
                foreach ($events as $event) {
                    $activity->recorded(ProposalActivitySource::TYPE, ProposalActivitySource::subjectIdOf(ProposalType::Event, (string) $event->getKey()));
                }
            });
    }
}
