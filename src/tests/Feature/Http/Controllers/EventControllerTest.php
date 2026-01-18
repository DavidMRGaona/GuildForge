<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_published_events(): void
    {
        EventModel::factory()->published()->count(3)->create();
        EventModel::factory()->draft()->count(2)->create();

        $response = $this->get('/eventos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events.data', 3)
        );
    }

    public function test_index_paginates_events(): void
    {
        EventModel::factory()->published()->count(15)->create();

        $response = $this->get('/eventos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events.data', 12)
                ->has('events.meta.currentPage')
                ->has('events.meta.lastPage')
                ->has('events.meta.perPage')
                ->has('events.meta.total')
        );
    }

    public function test_index_orders_events_by_date_descending(): void
    {
        $oldEvent = EventModel::factory()->published()->create([
            'title' => 'Old Event',
            'start_date' => now()->subDays(10),
        ]);
        $newEvent = EventModel::factory()->published()->create([
            'title' => 'New Event',
            'start_date' => now()->addDays(10),
        ]);

        $response = $this->get('/eventos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events.data', 2)
                ->where('events.data.0.title', 'New Event')
                ->where('events.data.1.title', 'Old Event')
        );
    }

    public function test_show_displays_single_published_event(): void
    {
        $event = EventModel::factory()->published()->create([
            'title' => 'Test Event',
            'slug' => 'test-event',
            'description' => 'Test description',
            'location' => 'Test Location',
        ]);

        $response = $this->get('/eventos/test-event');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Show')
                ->has('event')
                ->where('event.title', 'Test Event')
                ->where('event.slug', 'test-event')
                ->where('event.description', 'Test description')
                ->where('event.location', 'Test Location')
        );
    }

    public function test_show_returns_404_for_unpublished_event(): void
    {
        EventModel::factory()->draft()->create([
            'slug' => 'unpublished-event',
        ]);

        $response = $this->get('/eventos/unpublished-event');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_event(): void
    {
        $response = $this->get('/eventos/nonexistent-event');

        $response->assertStatus(404);
    }

    public function test_index_returns_events_with_new_fields(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Multi-day Event',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(3),
            'member_price' => 10.00,
            'non_member_price' => 15.00,
        ]);

        $response = $this->get('/eventos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.startDate', fn ($val) => str_contains($val, 'T'))
                ->where('events.data.0.endDate', fn ($val) => str_contains($val, 'T'))
                ->where('events.data.0.memberPrice', 10.00)
                ->where('events.data.0.nonMemberPrice', 15.00)
        );
    }

    public function test_show_displays_multi_day_event_dates(): void
    {
        EventModel::factory()->published()->create([
            'slug' => 'multi-day-event',
            'start_date' => '2025-06-15 10:00:00',
            'end_date' => '2025-06-17 18:00:00',
        ]);

        $response = $this->get('/eventos/multi-day-event');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Show')
                ->has('event.startDate')
                ->has('event.endDate')
        );
    }

    public function test_show_displays_event_prices(): void
    {
        EventModel::factory()->published()->create([
            'slug' => 'paid-event',
            'member_price' => 25.50,
            'non_member_price' => 35.00,
        ]);

        $response = $this->get('/eventos/paid-event');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('event.memberPrice', 25.50)
                ->where('event.nonMemberPrice', 35.00)
        );
    }

    public function test_show_displays_free_event(): void
    {
        EventModel::factory()->published()->create([
            'slug' => 'free-event',
            'member_price' => null,
            'non_member_price' => null,
        ]);

        $response = $this->get('/eventos/free-event');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('event.memberPrice', null)
                ->where('event.nonMemberPrice', null)
        );
    }
}
