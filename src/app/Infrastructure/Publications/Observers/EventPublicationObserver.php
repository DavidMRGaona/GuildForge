<?php

declare(strict_types=1);

namespace App\Infrastructure\Publications\Observers;

use App\Domain\Publications\Events\PublicationChanged;
use App\Domain\Publications\Events\PublicationRemoved;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Publications\Sources\EventPublicationSource;
use Illuminate\Support\Facades\DB;

/**
 * Tells publication listeners about the core's events once each change commits: a save of a
 * published event (or one that was just taken down) and a deletion. Drafts that stay drafts say
 * nothing. Listeners read the event again, so a save that changed nothing they show is harmless.
 */
final class EventPublicationObserver
{
    public function saved(EventModel $event): void
    {
        if (! $event->is_published && ! $event->wasChanged('is_published')) {
            return;
        }

        $id = (string) $event->getKey();

        DB::afterCommit(static fn () => event(new PublicationChanged(EventPublicationSource::TYPE, $id)));
    }

    public function deleted(EventModel $event): void
    {
        $id = (string) $event->getKey();

        DB::afterCommit(static fn () => event(new PublicationRemoved(EventPublicationSource::TYPE, $id)));
    }
}
