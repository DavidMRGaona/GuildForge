<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Entities\Event;
use App\Domain\Proposals\ValueObjects\ProposalReview;
use App\Domain\Repositories\EventRepositoryInterface;
use App\Domain\ValueObjects\EventId;
use App\Domain\ValueObjects\Price;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final readonly class EloquentEventRepository implements EventRepositoryInterface
{
    public function __construct(private ?TimezoneServiceInterface $timezone = null) {}

    /**
     * Any event, proposal drafts never submitted included (the proposals flow reads them here).
     */
    public function findById(EventId $id): ?Event
    {
        $model = EventModel::query()->includingUnsubmittedProposals()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findBySlug(string $slug): ?Event
    {
        $model = EventModel::query()->where('slug', $slug)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findUpcoming(int $limit = 10): Collection
    {
        return EventModel::query()
            ->where('start_date', '>=', now())
            ->where('is_published', true)
            ->orderBy('start_date')
            ->limit($limit)
            ->get()
            ->map(fn (EventModel $model): Event => $this->toDomain($model));
    }

    public function findPublished(): Collection
    {
        return EventModel::query()
            ->where('is_published', true)
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn (EventModel $model): Event => $this->toDomain($model));
    }

    public function findByDateRange(DateTimeImmutable $start, DateTimeImmutable $end): Collection
    {
        // The arguments are instants: bind them as storage-zone wall times, whatever zone they carry.
        $timezone = $this->timezone ?? app(TimezoneServiceInterface::class);
        $start = $timezone->toStorage($start)->format('Y-m-d H:i:s');
        $end = $timezone->toStorage($end)->format('Y-m-d H:i:s');

        return EventModel::query()
            ->where('is_published', true)
            ->where(function ($query) use ($start, $end) {
                // Event starts within range
                $query->whereBetween('start_date', [$start, $end])
                    // OR event ends within range (multi-day event)
                    ->orWhereBetween('end_date', [$start, $end])
                    // OR event spans the entire range
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->orderBy('start_date')
            ->get()
            ->map(fn (EventModel $model): Event => $this->toDomain($model));
    }

    public function save(Event $event): void
    {
        // Unsubmitted drafts included, or saving one would insert it again; the review columns are
        // not fillable, so they are written with forceFill()
        $model = EventModel::query()->includingUnsubmittedProposals()->find($event->id()->value) ?? new EventModel;

        $model->forceFill([...$this->toArray($event), ...$this->proposalColumns($event->proposal())]);
        $model->save();
    }

    public function delete(Event $event): void
    {
        EventModel::query()->includingUnsubmittedProposals()->where('id', $event->id()->value)->delete();
    }

    private function toDomain(EventModel $model): Event
    {
        return new Event(
            id: new EventId($model->id),
            title: $model->title,
            slug: new Slug($model->slug),
            description: $model->description,
            startDate: new DateTimeImmutable($model->start_date->toDateTimeString()),
            endDate: new DateTimeImmutable($model->end_date->toDateTimeString()),
            location: $model->location,
            imagePublicId: $model->image_public_id,
            memberPrice: $model->member_price !== null
                ? new Price((float) $model->member_price)
                : null,
            nonMemberPrice: $model->non_member_price !== null
                ? new Price((float) $model->non_member_price)
                : null,
            isPublished: $model->is_published,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at !== null
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
            proposal: $model->proposalReview(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalColumns(?ProposalReview $proposal): array
    {
        return [
            'proposal_status' => $proposal?->status,
            'proposed_by' => $proposal?->proposedBy,
            'proposal_note' => $proposal?->note,
            'proposal_reason' => $proposal?->reason,
            'submitted_at' => $proposal?->submittedAt,
            'reviewed_at' => $proposal?->reviewedAt,
            'reviewed_by' => $proposal?->reviewedBy,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Event $event): array
    {
        return [
            'id' => $event->id()->value,
            'title' => $event->title(),
            'slug' => $event->slug()->value,
            'description' => $event->description(),
            'start_date' => $event->startDate(),
            'location' => $event->location(),
            'image_public_id' => $event->imagePublicId(),
            'is_published' => $event->isPublished(),
            'end_date' => $event->endDate(),
            'member_price' => $event->memberPrice()?->value,
            'non_member_price' => $event->nonMemberPrice()?->value,
        ];
    }
}
