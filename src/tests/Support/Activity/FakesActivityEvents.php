<?php

declare(strict_types=1);

namespace Tests\Support\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use Illuminate\Support\Facades\Event;

/**
 * Watching what the core publishes (core#777). The publisher is scoped and keeps the dispatcher it
 * was built with, so one already built (by creating the data) would still dispatch for real: it is
 * dropped when the fake starts, and built again, with the fake, by the next change.
 */
trait FakesActivityEvents
{
    protected function fakeActivityEvents(): void
    {
        Event::fake([ActivityRecorded::class]);
        $this->app->forgetInstance(ActivityPublisherInterface::class);
    }

    protected function assertActivityPublished(string $sourceType, string $subjectId): void
    {
        Event::assertDispatched(
            ActivityRecorded::class,
            static fn (ActivityRecorded $event): bool => $event->sourceType === $sourceType && $event->subjectId === $subjectId,
        );
    }

    protected function assertNoActivityPublished(): void
    {
        Event::assertNotDispatched(ActivityRecorded::class);
    }
}
