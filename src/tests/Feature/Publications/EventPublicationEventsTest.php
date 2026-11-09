<?php

declare(strict_types=1);

namespace Tests\Feature\Publications;

use App\Domain\Publications\Events\PublicationChanged;
use App\Domain\Publications\Events\PublicationRemoved;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

final class EventPublicationEventsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([PublicationChanged::class, PublicationRemoved::class]);
    }

    public function test_publishing_an_event_tells_the_listeners(): void
    {
        $event = EventModel::factory()->published()->create();

        Event::assertDispatchedTimes(PublicationChanged::class, 1);
        Event::assertDispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->type === 'event' && $change->id === $event->id);
    }

    public function test_editing_a_published_event_tells_the_listeners(): void
    {
        $event = EventModel::factory()->published()->create();

        $event->update(['title' => 'Another title']);

        Event::assertDispatchedTimes(PublicationChanged::class, 2);
    }

    public function test_taking_an_event_down_tells_the_listeners(): void
    {
        $event = EventModel::factory()->published()->create();

        $event->update(['is_published' => false]);

        Event::assertDispatchedTimes(PublicationChanged::class, 2);
    }

    public function test_drafts_say_nothing(): void
    {
        $draft = EventModel::factory()->draft()->create();

        $draft->update(['title' => 'Still a draft']);

        Event::assertNotDispatched(PublicationChanged::class);
    }

    public function test_deleting_an_event_tells_the_listeners(): void
    {
        $event = EventModel::factory()->published()->create();

        $event->delete();

        Event::assertDispatched(PublicationRemoved::class, fn (PublicationRemoved $removal): bool => $removal->type === 'event' && $removal->id === $event->id);
    }

    public function test_nothing_is_said_about_a_change_rolled_back(): void
    {
        try {
            DB::transaction(function (): void {
                EventModel::factory()->published()->create();

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
            // The save is undone
        }

        Event::assertNotDispatched(PublicationChanged::class);
    }

    public function test_the_change_is_told_once_the_transaction_commits(): void
    {
        DB::transaction(function (): void {
            EventModel::factory()->published()->create();

            Event::assertNotDispatched(PublicationChanged::class);
        });

        Event::assertDispatchedTimes(PublicationChanged::class, 1);
    }
}
