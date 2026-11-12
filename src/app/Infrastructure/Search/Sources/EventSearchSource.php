<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Sources;

use App\Application\Search\Contracts\ChecksSearchHits;
use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Search\Sources\Concerns\ChecksPublishedHits;
use App\Infrastructure\Search\Support\PlainText;
use Illuminate\Support\Str;

final class EventSearchSource implements ChecksSearchHits, IndexedSearchSourceInterface
{
    use ChecksPublishedHits;

    public function type(): string
    {
        return 'events';
    }

    public function label(): string
    {
        return __('search.sources.events.label');
    }

    public function itemLabel(): string
    {
        return __('search.sources.events.item');
    }

    public function pastLabel(): string
    {
        return __('search.sources.events.past');
    }

    public function document(string $id): ?SearchDocumentDTO
    {
        // Keys are uuids: anything else matches no row, and PostgreSQL rejects it in the query
        if (! Str::isUuid($id)) {
            return null;
        }

        $event = EventModel::query()->with('tags')->whereKey($id)->where('is_published', true)->first();

        return $event instanceof EventModel ? $this->toDocument($event) : null;
    }

    /**
     * @return iterable<SearchDocumentDTO>
     */
    public function documents(): iterable
    {
        foreach (EventModel::query()->with('tags')->where('is_published', true)->lazyById(200) as $event) {
            yield $this->toDocument($event);
        }
    }

    /**
     * @return class-string<EventModel>
     */
    private function searchedModel(): string
    {
        return EventModel::class;
    }

    private function toDocument(EventModel $event): SearchDocumentDTO
    {
        $keywords = $event->tags->pluck('name')->implode(' ');

        return new SearchDocumentDTO(
            id: $event->id,
            title: $event->title,
            body: PlainText::from($event->description),
            url: route('events.show', $event->slug, false),
            keywords: $keywords !== '' ? $keywords : null,
            meta: $event->location,
            imagePublicId: $event->image_public_id,
            startsAt: $event->start_date->toImmutable(),
            endsAt: $event->end_date->toImmutable(),
        );
    }
}
