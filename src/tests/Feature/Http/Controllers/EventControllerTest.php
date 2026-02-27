<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EventControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

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

    public function test_index_includes_tags_in_response(): void
    {
        $tag1 = TagModel::factory()->forEvents()->create([
            'name' => 'Warhammer 40k',
            'slug' => 'warhammer-40k',
        ]);
        $tag2 = TagModel::factory()->forEvents()->create([
            'name' => 'Tournament',
            'slug' => 'tournament',
        ]);

        TagModel::factory()->forArticles()->create();

        $response = $this->get('/eventos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('tags', 2)
                ->where('tags.0.name', fn ($val) => in_array($val, ['Warhammer 40k', 'Tournament']))
                ->where('tags.1.name', fn ($val) => in_array($val, ['Warhammer 40k', 'Tournament']))
        );
    }

    public function test_index_includes_current_tag_filter(): void
    {
        TagModel::factory()->forEvents()->create([
            'slug' => 'warhammer-40k',
        ]);

        $response = $this->get('/eventos?tags=warhammer-40k');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Index')
                ->where('currentTags', ['warhammer-40k'])
        );
    }

    public function test_index_filters_events_by_tag(): void
    {
        $tag = TagModel::factory()->forEvents()->create([
            'name' => 'Warhammer 40k',
            'slug' => 'warhammer-40k',
        ]);

        $taggedEvent = EventModel::factory()->published()->create([
            'title' => 'Warhammer Tournament',
        ]);
        $taggedEvent->tags()->attach($tag->id);

        $untaggedEvent = EventModel::factory()->published()->create([
            'title' => 'D&D Campaign',
        ]);

        $response = $this->get('/eventos?tags=warhammer-40k');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.title', 'Warhammer Tournament')
        );
    }

    public function test_index_shows_all_events_when_no_tag_filter(): void
    {
        $tag = TagModel::factory()->forEvents()->create();

        $taggedEvent = EventModel::factory()->published()->create(['title' => 'Tagged Event']);
        $taggedEvent->tags()->attach($tag->id);

        $untaggedEvent = EventModel::factory()->published()->create(['title' => 'Untagged Event']);

        $response = $this->get('/eventos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events.data', 2)
        );
    }

    public function test_index_returns_empty_when_tag_has_no_events(): void
    {
        $tag = TagModel::factory()->forEvents()->create([
            'slug' => 'unused-tag',
        ]);

        EventModel::factory()->published()->create();

        $response = $this->get('/eventos?tags=unused-tag');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events.data', 0)
        );
    }

    public function test_show_includes_event_tags(): void
    {
        $tag1 = TagModel::factory()->forEvents()->create([
            'name' => 'Warhammer 40k',
            'slug' => 'warhammer-40k',
        ]);
        $tag2 = TagModel::factory()->forEvents()->create([
            'name' => 'Tournament',
            'slug' => 'tournament',
        ]);

        $event = EventModel::factory()->published()->create([
            'slug' => 'test-event',
        ]);
        $event->tags()->attach([$tag1->id, $tag2->id]);

        $response = $this->get('/eventos/test-event');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Show')
                ->has('event.tags', 2)
                ->where('event.tags.0.name', fn ($val) => in_array($val, ['Warhammer 40k', 'Tournament']))
                ->where('event.tags.1.name', fn ($val) => in_array($val, ['Warhammer 40k', 'Tournament']))
        );
    }

    public function test_show_displays_event_without_tags(): void
    {
        $event = EventModel::factory()->published()->create([
            'slug' => 'untagged-event',
        ]);

        $response = $this->get('/eventos/untagged-event');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Show')
                ->has('event.tags', 0)
        );
    }

    public function test_index_filters_by_child_tag(): void
    {
        $parent = TagModel::factory()->forEvents()->create([
            'name' => 'Miniature Games',
            'slug' => 'miniature-games',
        ]);

        $child = TagModel::factory()->forEvents()->withParent($parent)->create([
            'name' => 'Warhammer 40k',
            'slug' => 'warhammer-40k',
        ]);

        $event = EventModel::factory()->published()->create([
            'title' => 'Warhammer Tournament',
        ]);
        $event->tags()->attach($child->id);

        $response = $this->get('/eventos?tags=warhammer-40k');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.title', 'Warhammer Tournament')
        );
    }

    public function test_show_returns_download_links(): void
    {
        $downloadLinks = [
            ['label' => 'Bases del torneo', 'url' => 'https://example.com/rules.pdf', 'description' => 'Reglas'],
            ['label' => 'Horarios', 'url' => 'https://example.com/schedule.pdf', 'description' => ''],
        ];

        EventModel::factory()->published()->create([
            'slug' => 'event-with-downloads',
            'download_links' => $downloadLinks,
        ]);

        $response = $this->get('/eventos/event-with-downloads');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Show')
                ->has('event.downloadLinks', 2)
                ->where('event.downloadLinks.0.label', 'Bases del torneo')
                ->where('event.downloadLinks.0.url', 'https://example.com/rules.pdf')
                ->where('event.downloadLinks.1.label', 'Horarios')
        );
    }

    public function test_show_returns_empty_download_links_when_none(): void
    {
        EventModel::factory()->published()->create([
            'slug' => 'event-no-downloads',
            'download_links' => null,
        ]);

        $response = $this->get('/eventos/event-no-downloads');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Show')
                ->has('event.downloadLinks', 0)
        );
    }

    public function test_show_returns_sanitized_html_description(): void
    {
        EventModel::factory()->published()->create([
            'slug' => 'secure-event',
            'description' => '<p>Event info</p><script>alert("hack")</script>',
        ]);

        $response = $this->get('/eventos/secure-event');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('event.description', fn ($val) => str_contains($val, '<p>Event info</p>'))
                ->where('event.description', fn ($val) => ! str_contains($val, '<script>'))
        );
    }
}
