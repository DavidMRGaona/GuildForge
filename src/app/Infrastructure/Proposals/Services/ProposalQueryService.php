<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Services;

use App\Application\Proposals\DTOs\OwnProposalDTO;
use App\Application\Proposals\DTOs\ProposalLimitsDTO;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Application\Proposals\Services\ProposalQueryServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final readonly class ProposalQueryService implements ProposalQueryServiceInterface
{
    /** Same key the service counts submissions under */
    private const string RATE_KEY = 'proposals-submit:';

    public function __construct(
        private ProposalSettingsServiceInterface $settings,
        private EventProposalRequestServiceInterface $requests,
    ) {}

    public function ownedBy(string $userId): array
    {
        if (! Str::isUuid($userId)) {
            return [];
        }

        $proposals = [];

        foreach (ProposalType::cases() as $type) {
            foreach ($this->query($type)->where('proposed_by', $userId)->with('tags:id')->get() as $model) {
                $proposal = $this->toDto($type, $model);

                if ($proposal instanceof OwnProposalDTO) {
                    $proposals[] = $proposal;
                }
            }
        }

        usort(
            $proposals,
            static fn (OwnProposalDTO $a, OwnProposalDTO $b): int => ($b->updatedAt?->getTimestamp() ?? 0) <=> ($a->updatedAt?->getTimestamp() ?? 0),
        );

        return $proposals;
    }

    public function findOwned(string $userId, ProposalType $type, string $id): ?OwnProposalDTO
    {
        if (! Str::isUuid($userId) || ! Str::isUuid($id)) {
            return null;
        }

        $model = $this->query($type)->where('proposed_by', $userId)->with('tags:id')->whereKey($id)->first();

        return $model instanceof EventModel || $model instanceof ArticleModel ? $this->toDto($type, $model) : null;
    }

    public function limitsOf(string $userId): ProposalLimitsDTO
    {
        $settings = $this->settings->settings();

        return new ProposalLimitsDTO(
            pendingUsed: $this->count($userId, ProposalStatus::PendingReview),
            pendingMax: $settings->maxPending,
            draftsUsed: $this->count($userId, ProposalStatus::Draft),
            draftsMax: $settings->maxOpenDrafts,
            submissionsLeftThisHour: RateLimiter::remaining(self::RATE_KEY.$userId, $settings->submissionsPerHour),
            submissionsPerHour: $settings->submissionsPerHour,
        );
    }

    public function proposerNameOfEvent(string $eventId): ?string
    {
        if (! $this->settings->settings()->events->showProposer || ! Str::isUuid($eventId)) {
            return null;
        }

        $event = EventModel::query()
            ->where('proposal_status', ProposalStatus::Approved->value)
            ->whereNotNull('proposed_by')
            ->with('proposer')
            ->find($eventId);

        $proposer = $event?->proposer;

        if (! $proposer instanceof UserModel || $proposer->anonymized_at !== null) {
            return null;
        }

        $name = trim((string) ($proposer->display_name ?? ''));
        $name = $name !== '' ? $name : trim((string) $proposer->name);

        return $name !== '' ? $name : null;
    }

    private function count(string $userId, ProposalStatus $status): int
    {
        $count = 0;

        foreach (ProposalType::cases() as $type) {
            $count += $this->query($type)
                ->where('proposed_by', $userId)
                ->where('proposal_status', $status->value)
                ->count();
        }

        return $count;
    }

    /**
     * Every proposal, never-sent drafts included: they are their author's.
     *
     * @return Builder<EventModel>|Builder<ArticleModel>
     */
    private function query(ProposalType $type): Builder
    {
        return match ($type) {
            ProposalType::Event => EventModel::query()->includingUnsubmittedProposals()->whereNotNull('proposal_status'),
            ProposalType::Article => ArticleModel::query()->includingUnsubmittedProposals()->whereNotNull('proposal_status'),
        };
    }

    private function toDto(ProposalType $type, EventModel|ArticleModel $model): ?OwnProposalDTO
    {
        $status = $model->proposalStatus();

        if (! $status instanceof ProposalStatus) {
            return null;
        }

        $review = $model->proposalReview();
        $isEvent = $model instanceof EventModel;

        /** @var list<string> $tagIds */
        $tagIds = $model->tags->pluck('id')->map(static fn (mixed $id): string => (string) $id)->values()->all();

        return new OwnProposalDTO(
            type: $type,
            id: (string) $model->getKey(),
            status: $status,
            title: (string) $model->getAttribute('title'),
            body: $this->text($model->getAttribute($isEvent ? 'description' : 'content')),
            excerpt: $isEvent ? null : $this->text($model->getAttribute('excerpt')),
            location: $isEvent ? $this->text($model->getAttribute('location')) : null,
            startDate: $isEvent ? $this->instant($model->getAttribute('start_date')) : null,
            endDate: $isEvent ? $this->instant($model->getAttribute('end_date')) : null,
            imagePublicId: $this->text($model->getAttribute($isEvent ? 'image_public_id' : 'featured_image_public_id')),
            tagIds: $tagIds,
            note: $review?->note,
            reason: $review?->reason,
            submittedAt: $review?->submittedAt,
            reviewedAt: $review?->reviewedAt,
            updatedAt: $this->instant($model->getAttribute('updated_at')),
            slug: $status === ProposalStatus::Approved ? $this->text($model->getAttribute('slug')) : null,
            requests: $isEvent ? $this->requests->forEvent((string) $model->getKey()) : [],
        );
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : null;
    }
}
