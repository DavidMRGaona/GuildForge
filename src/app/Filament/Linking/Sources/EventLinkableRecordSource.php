<?php

declare(strict_types=1);

namespace App\Filament\Linking\Sources;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The core's events as linkable records.
 *
 * Published events are public, so every viewer finds them. Drafts (unpublished events and
 * proposals under review or rejected) only for who lists events in the panel (events.view_any);
 * drafts never submitted from the web stay hidden, as in the panel. The panel URL is the edit
 * page, given only to who may open the panel and edit the event.
 */
final readonly class EventLinkableRecordSource implements LinkableRecordSourceInterface
{
    public const string TYPE = 'event';

    /** Words of the query that are searched; any beyond are ignored. */
    private const int MAX_QUERY_TERMS = 6;

    /** Backslash escapes the LIKE wildcards; SQLite has no default escape character. */
    private const string TITLE_CONTAINS = "LOWER(title) LIKE ? ESCAPE '\\'";

    public function __construct(private AuthorizationServiceInterface $authorization) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('linking.types.event');
    }

    public function search(string $query, string $viewerId, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $viewer = $this->viewer($viewerId);
        $events = $this->visibleTo($viewer);

        foreach ($this->terms($query) as $term) {
            $events->whereRaw(self::TITLE_CONTAINS, ['%'.$this->escapeLike($term).'%']);
        }

        $found = $events->orderByDesc('start_date')->orderBy('id')->limit($limit)->get();

        return array_values($found->map(fn (EventModel $event): LinkableRecordDTO => $this->toRecord($event, $viewer))->all());
    }

    public function find(string $id, string $viewerId): ?LinkableRecordDTO
    {
        // Keys are uuids: anything else matches no row, and PostgreSQL rejects it in the query
        if (! Str::isUuid($id)) {
            return null;
        }

        $viewer = $this->viewer($viewerId);
        $event = $this->visibleTo($viewer)->whereKey($id)->first();

        return $event instanceof EventModel ? $this->toRecord($event, $viewer) : null;
    }

    /**
     * @return Builder<EventModel>
     */
    private function visibleTo(?UserModel $viewer): Builder
    {
        $events = EventModel::query();

        if (! $viewer instanceof UserModel || ! $this->authorization->can($viewer, 'events.view_any')) {
            $events->where('is_published', true);
        }

        return $events;
    }

    private function viewer(string $viewerId): ?UserModel
    {
        if (! Str::isUuid($viewerId)) {
            return null;
        }

        $viewer = UserModel::query()->find($viewerId);

        return $viewer instanceof UserModel ? $viewer : null;
    }

    private function toRecord(EventModel $event, ?UserModel $viewer): LinkableRecordDTO
    {
        return new LinkableRecordDTO(
            type: self::TYPE,
            id: $event->id,
            title: $event->title,
            date: $event->start_date->toImmutable(),
            panelUrl: $this->panelUrl($event, $viewer),
        );
    }

    private function panelUrl(EventModel $event, ?UserModel $viewer): ?string
    {
        if (! $viewer instanceof UserModel || ! $this->authorization->can($viewer, 'admin.access')) {
            return null;
        }

        if (! Gate::forUser($viewer)->allows('update', $event)) {
            return null;
        }

        return EventResource::getUrl('edit', ['record' => $event]);
    }

    /**
     * Lower-cased words of the query; every one must appear in the title.
     *
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY);

        return array_slice($words === false ? [] : $words, 0, self::MAX_QUERY_TERMS);
    }

    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
