<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\Event;
use App\Domain\Repositories\EventRepositoryInterface;
use App\Domain\ValueObjects\EventId;
use App\Domain\ValueObjects\Price;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final readonly class EloquentEventRepository implements EventRepositoryInterface
{
    public function findById(EventId $id): ?Event
    {
        $model = EventModel::query()->find($id->value);

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
        return EventModel::query()
            ->where('is_published', true)
            ->where(function ($query) use ($start, $end) {
                // Event starts within range
                $query->whereBetween('start_date', [$start, $end])
                    // OR event ends within range (multi-day event)
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNotNull('end_date')
                            ->whereBetween('end_date', [$start, $end]);
                    })
                    // OR event spans the entire range
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start_date', '<=', $start)
                            ->whereNotNull('end_date')
                            ->where('end_date', '>=', $end);
                    });
            })
            ->orderBy('start_date')
            ->get()
            ->map(fn (EventModel $model): Event => $this->toDomain($model));
    }

    public function save(Event $event): void
    {
        EventModel::query()->updateOrCreate(
            ['id' => $event->id()->value],
            $this->toArray($event),
        );
    }

    public function delete(Event $event): void
    {
        EventModel::query()->where('id', $event->id()->value)->delete();
    }

    private function toDomain(EventModel $model): Event
    {
        return new Event(
            id: new EventId($model->id),
            title: $model->title,
            slug: new Slug($model->slug),
            description: $model->description,
            startDate: new DateTimeImmutable($model->start_date->toDateTimeString()),
            location: $model->location,
            imagePublicId: $model->image_public_id,
            isPublished: $model->is_published,
            endDate: $model->end_date !== null
                ? new DateTimeImmutable($model->end_date->toDateTimeString())
                : null,
            memberPrice: $model->member_price !== null
                ? new Price((float) $model->member_price)
                : null,
            nonMemberPrice: $model->non_member_price !== null
                ? new Price((float) $model->non_member_price)
                : null,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at !== null
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
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
            'start_date' => $event->startDate()->format('Y-m-d H:i:s'),
            'location' => $event->location(),
            'image_public_id' => $event->imagePublicId(),
            'is_published' => $event->isPublished(),
            'end_date' => $event->endDate()?->format('Y-m-d H:i:s'),
            'member_price' => $event->memberPrice()?->value,
            'non_member_price' => $event->nonMemberPrice()?->value,
        ];
    }
}
