<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Publications;

use App\Application\Publications\Enums\PublicationState;
use App\Application\Publications\Services\PublicationSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Publications\Sources\EventPublicationSource;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EventPublicationSourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_core_registers_its_event_source(): void
    {
        $source = $this->app->make(PublicationSourceRegistryInterface::class)->source('event');

        $this->assertInstanceOf(EventPublicationSource::class, $source);
    }

    public function test_a_published_event_is_a_publication_with_its_page_and_dates(): void
    {
        $event = EventModel::factory()->published()->upcoming()->create([
            'title' => 'Example event',
            'description' => '<p>First line</p><p>Second &amp; last</p>',
        ]);

        $publication = $this->source()->find($event->id);

        $this->assertNotNull($publication);
        $this->assertSame('event', $publication->type);
        $this->assertSame($event->id, $publication->id);
        $this->assertSame('Example event', $publication->title);
        $this->assertSame('First line Second & last', $publication->summary);
        $this->assertSame(route('events.show', $event->slug), $publication->url);
        $this->assertStringStartsWith('http', $publication->url);
        $this->assertSame(PublicationState::Active, $publication->state);
        $this->assertSame($event->start_date->getTimestamp(), $publication->startsAt?->getTimestamp());
        $this->assertSame($event->end_date->getTimestamp(), $publication->endsAt?->getTimestamp());
        $this->assertFalse($publication->isFull);
        $this->assertFalse($publication->registrationOpen);
        $this->assertSame([], $publication->topics);
    }

    public function test_an_event_that_is_over_has_ended(): void
    {
        $event = EventModel::factory()->published()->past()->create();

        $this->assertSame(PublicationState::Ended, $this->source()->find($event->id)?->state);
    }

    public function test_drafts_unknown_ids_and_non_uuids_are_not_publications(): void
    {
        $draft = EventModel::factory()->draft()->upcoming()->create();

        $this->assertNull($this->source()->find($draft->id));
        $this->assertNull($this->source()->find((string) Str::uuid()));
        $this->assertNull($this->source()->find('not-a-uuid'));
    }

    public function test_published_ids_are_the_published_events_not_over_yet(): void
    {
        $upcoming = EventModel::factory()->published()->upcoming()->create();
        EventModel::factory()->published()->past()->create();
        EventModel::factory()->draft()->upcoming()->create();

        $ids = iterator_to_array($this->source()->publishedIds(), false);

        $this->assertSame([$upcoming->id], $ids);
    }

    private function source(): EventPublicationSource
    {
        return $this->app->make(EventPublicationSource::class);
    }
}
