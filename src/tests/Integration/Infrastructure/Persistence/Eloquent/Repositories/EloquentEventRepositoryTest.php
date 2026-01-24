<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\Event;
use App\Domain\Repositories\EventRepositoryInterface;
use App\Domain\ValueObjects\EventId;
use App\Domain\ValueObjects\Price;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentEventRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentEventRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentEventRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentEventRepository;
    }

    public function test_it_implements_event_repository_interface(): void
    {
        $this->assertInstanceOf(EventRepositoryInterface::class, $this->repository);
    }

    public function test_it_finds_event_by_id(): void
    {
        $model = EventModel::factory()->create([
            'title' => 'Test Event',
            'slug' => 'test-event',
        ]);

        $event = $this->repository->findById(new EventId($model->id));

        $this->assertNotNull($event);
        $this->assertEquals($model->id, $event->id()->value);
        $this->assertEquals('Test Event', $event->title());
        $this->assertEquals('test-event', $event->slug()->value);
    }

    public function test_it_returns_null_when_not_found(): void
    {
        $event = $this->repository->findById(EventId::generate());

        $this->assertNull($event);
    }

    public function test_it_finds_event_by_slug(): void
    {
        $model = EventModel::factory()->create([
            'title' => 'Warhammer Tournament',
            'slug' => 'warhammer-tournament',
        ]);

        $event = $this->repository->findBySlug('warhammer-tournament');

        $this->assertNotNull($event);
        $this->assertEquals($model->id, $event->id()->value);
        $this->assertEquals('Warhammer Tournament', $event->title());
    }

    public function test_it_returns_null_when_slug_not_found(): void
    {
        $event = $this->repository->findBySlug('non-existent-slug');

        $this->assertNull($event);
    }

    public function test_it_finds_upcoming_events(): void
    {
        EventModel::factory()->upcoming()->published()->count(3)->create();
        EventModel::factory()->past()->published()->count(2)->create();
        EventModel::factory()->upcoming()->draft()->count(2)->create();

        $events = $this->repository->findUpcoming();

        $this->assertCount(3, $events);
        $events->each(function (Event $event) {
            $this->assertTrue($event->isUpcoming());
            $this->assertTrue($event->isPublished());
        });
    }

    public function test_it_limits_upcoming_events(): void
    {
        EventModel::factory()->upcoming()->published()->count(10)->create();

        $events = $this->repository->findUpcoming(5);

        $this->assertCount(5, $events);
    }

    public function test_it_orders_upcoming_events_by_date(): void
    {
        EventModel::factory()->published()->create([
            'start_date' => now()->addDays(10),
            'title' => 'Third Event',
        ]);
        EventModel::factory()->published()->create([
            'start_date' => now()->addDays(1),
            'title' => 'First Event',
        ]);
        EventModel::factory()->published()->create([
            'start_date' => now()->addDays(5),
            'title' => 'Second Event',
        ]);

        $events = $this->repository->findUpcoming();

        $this->assertEquals('First Event', $events->first()->title());
        $this->assertEquals('Third Event', $events->last()->title());
    }

    public function test_it_finds_published_events(): void
    {
        EventModel::factory()->published()->count(4)->create();
        EventModel::factory()->draft()->count(3)->create();

        $events = $this->repository->findPublished();

        $this->assertCount(4, $events);
        $events->each(function (Event $event) {
            $this->assertTrue($event->isPublished());
        });
    }

    public function test_it_saves_new_event(): void
    {
        $id = EventId::generate();
        $event = new Event(
            id: $id,
            title: 'New Event',
            slug: new Slug('new-event'),
            description: 'A brand new event.',
            startDate: new DateTimeImmutable('+1 week'),
            location: 'Main Hall',
            imagePublicId: 'events/new-event.jpg',
            isPublished: true,
        );

        $this->repository->save($event);

        $this->assertDatabaseHas('events', [
            'id' => $id->value,
            'title' => 'New Event',
            'slug' => 'new-event',
            'description' => 'A brand new event.',
            'location' => 'Main Hall',
            'image_public_id' => 'events/new-event.jpg',
            'is_published' => true,
        ]);
    }

    public function test_it_updates_existing_event(): void
    {
        $model = EventModel::factory()->create([
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'is_published' => false,
        ]);

        $event = new Event(
            id: new EventId($model->id),
            title: 'Updated Title',
            slug: new Slug('updated-slug'),
            description: 'Updated description.',
            startDate: new DateTimeImmutable('+2 weeks'),
            location: 'New Location',
            isPublished: true,
        );

        $this->repository->save($event);

        $this->assertDatabaseHas('events', [
            'id' => $model->id,
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
            'description' => 'Updated description.',
            'location' => 'New Location',
            'is_published' => true,
        ]);

        $this->assertDatabaseMissing('events', [
            'title' => 'Original Title',
        ]);
    }

    public function test_it_deletes_event(): void
    {
        $model = EventModel::factory()->create();
        $event = $this->repository->findById(new EventId($model->id));

        $this->assertNotNull($event);

        $this->repository->delete($event);

        $this->assertDatabaseMissing('events', [
            'id' => $model->id,
        ]);
    }

    public function test_it_saves_event_with_end_date(): void
    {
        $id = EventId::generate();
        $startDate = new DateTimeImmutable('+1 week');
        $endDate = new DateTimeImmutable('+10 days');

        $event = new Event(
            id: $id,
            title: 'Multi-day Event',
            slug: new Slug('multi-day-event'),
            description: 'This event spans multiple days.',
            startDate: $startDate,
            endDate: $endDate,
            isPublished: true,
        );

        $this->repository->save($event);

        $this->assertDatabaseHas('events', [
            'id' => $id->value,
            'title' => 'Multi-day Event',
        ]);

        $savedEvent = $this->repository->findById($id);
        $this->assertNotNull($savedEvent);
        $this->assertNotNull($savedEvent->endDate());
        $this->assertEquals($endDate->format('Y-m-d'), $savedEvent->endDate()->format('Y-m-d'));
    }

    public function test_it_saves_event_with_prices(): void
    {
        $id = EventId::generate();
        $memberPrice = new Price(15.50);
        $nonMemberPrice = new Price(20.00);

        $event = new Event(
            id: $id,
            title: 'Priced Event',
            slug: new Slug('priced-event'),
            description: 'This event has pricing.',
            startDate: new DateTimeImmutable('+1 week'),
            memberPrice: $memberPrice,
            nonMemberPrice: $nonMemberPrice,
            isPublished: true,
        );

        $this->repository->save($event);

        $this->assertDatabaseHas('events', [
            'id' => $id->value,
            'member_price' => 15.50,
            'non_member_price' => 20.00,
        ]);

        $savedEvent = $this->repository->findById($id);
        $this->assertNotNull($savedEvent);
        $this->assertNotNull($savedEvent->memberPrice());
        $this->assertEquals(15.50, $savedEvent->memberPrice()->value);
        $this->assertEquals(20.00, $savedEvent->nonMemberPrice()->value);
    }

    public function test_to_domain_maps_new_fields_correctly(): void
    {
        $model = EventModel::factory()->create([
            'title' => 'Complete Event',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(9),
            'member_price' => 12.50,
            'non_member_price' => 18.00,
        ]);

        $event = $this->repository->findById(new EventId($model->id));

        $this->assertNotNull($event);
        $this->assertNotNull($event->endDate());
        $this->assertNotNull($event->memberPrice());
        $this->assertNotNull($event->nonMemberPrice());
        $this->assertEquals(12.50, $event->memberPrice()->value);
        $this->assertEquals(18.00, $event->nonMemberPrice()->value);
        $this->assertTrue($event->isMultiDay());
    }

    public function test_find_by_date_range_finds_events_starting_in_range(): void
    {
        // Create events with start dates in the range
        EventModel::factory()->published()->create([
            'title' => 'Event in Range 1',
            'start_date' => now()->addDays(5),
            'end_date' => null,
        ]);
        EventModel::factory()->published()->create([
            'title' => 'Event in Range 2',
            'start_date' => now()->addDays(8),
            'end_date' => null,
        ]);

        // Create events outside the range (explicitly single-day)
        EventModel::factory()->published()->create([
            'title' => 'Event Before Range',
            'start_date' => now()->addDays(1),
            'end_date' => null,
        ]);
        EventModel::factory()->published()->create([
            'title' => 'Event After Range',
            'start_date' => now()->addDays(15),
            'end_date' => null,
        ]);

        $start = new DateTimeImmutable('+3 days');
        $end = new DateTimeImmutable('+10 days');

        $events = $this->repository->findByDateRange($start, $end);

        $this->assertCount(2, $events);
        $this->assertTrue($events->contains(fn (Event $e) => $e->title() === 'Event in Range 1'));
        $this->assertTrue($events->contains(fn (Event $e) => $e->title() === 'Event in Range 2'));
    }

    public function test_find_by_date_range_finds_events_ending_in_range(): void
    {
        // Create multi-day event that starts before range but ends within
        EventModel::factory()->published()->create([
            'title' => 'Multi-day Event',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(6),
        ]);

        // Event completely before range
        EventModel::factory()->published()->create([
            'title' => 'Event Before',
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(1),
        ]);

        $start = new DateTimeImmutable('+3 days');
        $end = new DateTimeImmutable('+10 days');

        $events = $this->repository->findByDateRange($start, $end);

        $this->assertCount(1, $events);
        $this->assertEquals('Multi-day Event', $events->first()->title());
    }

    public function test_find_by_date_range_finds_events_spanning_range(): void
    {
        // Create event that starts before and ends after the range
        EventModel::factory()->published()->create([
            'title' => 'Spanning Event',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(15),
        ]);

        $start = new DateTimeImmutable('+3 days');
        $end = new DateTimeImmutable('+10 days');

        $events = $this->repository->findByDateRange($start, $end);

        $this->assertCount(1, $events);
        $this->assertEquals('Spanning Event', $events->first()->title());
    }

    public function test_find_by_date_range_excludes_events_outside_range(): void
    {
        // Events completely before range
        EventModel::factory()->published()->create([
            'title' => 'Past Event',
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(5),
        ]);

        // Events completely after range
        EventModel::factory()->published()->create([
            'title' => 'Future Event',
            'start_date' => now()->addDays(20),
        ]);

        $start = new DateTimeImmutable('+3 days');
        $end = new DateTimeImmutable('+10 days');

        $events = $this->repository->findByDateRange($start, $end);

        $this->assertCount(0, $events);
    }

    public function test_find_by_date_range_excludes_unpublished_events(): void
    {
        // Published event in range
        EventModel::factory()->published()->create([
            'title' => 'Published Event',
            'start_date' => now()->addDays(5),
        ]);

        // Unpublished event in range
        EventModel::factory()->draft()->create([
            'title' => 'Draft Event',
            'start_date' => now()->addDays(6),
        ]);

        $start = new DateTimeImmutable('+3 days');
        $end = new DateTimeImmutable('+10 days');

        $events = $this->repository->findByDateRange($start, $end);

        $this->assertCount(1, $events);
        $this->assertEquals('Published Event', $events->first()->title());
    }

    public function test_find_by_date_range_orders_by_start_date(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Third Event',
            'start_date' => now()->addDays(9),
        ]);
        EventModel::factory()->published()->create([
            'title' => 'First Event',
            'start_date' => now()->addDays(4),
        ]);
        EventModel::factory()->published()->create([
            'title' => 'Second Event',
            'start_date' => now()->addDays(6),
        ]);

        $start = new DateTimeImmutable('+3 days');
        $end = new DateTimeImmutable('+10 days');

        $events = $this->repository->findByDateRange($start, $end);

        $this->assertCount(3, $events);
        $this->assertEquals('First Event', $events->first()->title());
        $this->assertEquals('Third Event', $events->last()->title());
    }
}
