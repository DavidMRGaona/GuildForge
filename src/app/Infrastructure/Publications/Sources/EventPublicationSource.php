<?php

declare(strict_types=1);

namespace App\Infrastructure\Publications\Sources;

use App\Application\Publications\Contracts\PublicationSourceInterface;
use App\Application\Publications\DTOs\PublicationDTO;
use App\Application\Publications\Enums\PublicationState;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Search\Support\PlainText;
use Illuminate\Support\Str;

/**
 * The core's events as publications. Events are never cancelled (they are unpublished or deleted)
 * and take no registrations here: places and sign-ups belong to whichever module adds them.
 */
final readonly class EventPublicationSource implements PublicationSourceInterface
{
    public const string TYPE = 'event';

    public function __construct(private TimezoneServiceInterface $timezone) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function find(string $id): ?PublicationDTO
    {
        // Keys are uuids: anything else matches no row, and PostgreSQL rejects it in the query
        if (! Str::isUuid($id)) {
            return null;
        }

        $event = EventModel::query()->whereKey($id)->where('is_published', true)->first();

        return $event instanceof EventModel ? $this->toPublication($event) : null;
    }

    public function publishedIds(): iterable
    {
        // Bindings bypass the model's storage-zone cast, so the bound is converted by hand
        $now = $this->timezone->toStorage(now())->format('Y-m-d H:i:s');
        $events = EventModel::query()->select('id')->where('is_published', true)->where('end_date', '>=', $now)->lazyById(200);

        foreach ($events as $event) {
            yield (string) $event->getKey();
        }
    }

    private function toPublication(EventModel $event): PublicationDTO
    {
        return new PublicationDTO(
            type: self::TYPE,
            id: $event->id,
            title: $event->title,
            summary: PlainText::from($event->description),
            url: route('events.show', $event->slug),
            state: $event->end_date->isPast() ? PublicationState::Ended : PublicationState::Active,
            startsAt: $event->start_date->toImmutable(),
            endsAt: $event->end_date->toImmutable(),
        );
    }
}
