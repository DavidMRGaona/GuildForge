<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class CalendarControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_returns_json_response(): void
    {
        EventModel::factory()->published()->create([
            'start_date' => '2025-01-15 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure([
            '*' => [
                'id',
                'title',
                'slug',
                'description',
                'start',
                'end',
                'location',
                'imagePublicId',
                'memberPrice',
                'nonMemberPrice',
                'url',
            ],
        ]);
    }

    public function test_index_returns_only_published_events(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Published Event',
            'start_date' => '2025-01-15 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        EventModel::factory()->draft()->create([
            'title' => 'Draft Event',
            'start_date' => '2025-01-16 10:00:00',
            'end_date' => '2025-01-16 18:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['title' => 'Published Event']);
        $response->assertJsonMissing(['title' => 'Draft Event']);
    }

    public function test_index_filters_events_by_date_range(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Event in Range',
            'start_date' => '2025-01-15 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        EventModel::factory()->published()->create([
            'title' => 'Event Before Range',
            'start_date' => '2024-12-15 10:00:00',
            'end_date' => '2024-12-15 18:00:00',
        ]);

        EventModel::factory()->published()->create([
            'title' => 'Event After Range',
            'start_date' => '2025-02-15 10:00:00',
            'end_date' => '2025-02-15 18:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['title' => 'Event in Range']);
        $response->assertJsonMissing(['title' => 'Event Before Range']);
        $response->assertJsonMissing(['title' => 'Event After Range']);
    }

    public function test_index_includes_multi_day_events_spanning_range(): void
    {
        // Event starting before range but ending within range
        EventModel::factory()->published()->create([
            'title' => 'Multi-day Event Starting Before',
            'start_date' => '2024-12-28 10:00:00',
            'end_date' => '2025-01-05 18:00:00',
        ]);

        // Event starting within range and ending after
        EventModel::factory()->published()->create([
            'title' => 'Multi-day Event Ending After',
            'start_date' => '2025-01-28 10:00:00',
            'end_date' => '2025-02-03 18:00:00',
        ]);

        // Event completely within range
        EventModel::factory()->published()->create([
            'title' => 'Multi-day Event Within Range',
            'start_date' => '2025-01-10 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
        $response->assertJsonFragment(['title' => 'Multi-day Event Starting Before']);
        $response->assertJsonFragment(['title' => 'Multi-day Event Ending After']);
        $response->assertJsonFragment(['title' => 'Multi-day Event Within Range']);
    }

    public function test_index_returns_correct_fullcalendar_format(): void
    {
        $event = EventModel::factory()->published()->create([
            'title' => 'FullCalendar Event',
            'slug' => 'fullcalendar-event',
            'start_date' => '2025-01-15 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonCount(1);

        $json = $response->json();

        $this->assertArrayHasKey('id', $json[0]);
        $this->assertEquals($event->id, $json[0]['id']);
        $this->assertEquals('FullCalendar Event', $json[0]['title']);
        $this->assertStringContainsString('2025-01-15T10:00:00', $json[0]['start']);
        $this->assertStringContainsString('2025-01-15T18:00:00', $json[0]['end']);
        $this->assertEquals('/eventos/fullcalendar-event', $json[0]['url']);
    }

    public function test_index_handles_single_day_events(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Single Day Event',
            'start_date' => '2025-01-15 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonCount(1);

        $json = $response->json();

        $this->assertStringContainsString('2025-01-15T10:00:00', $json[0]['start']);
        $this->assertStringContainsString('2025-01-15T18:00:00', $json[0]['end']);
    }

    public function test_index_requires_start_parameter(): void
    {
        $response = $this->getJson('/eventos/calendario?end=2025-01-31');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start']);
    }

    public function test_index_requires_end_parameter(): void
    {
        $response = $this->getJson('/eventos/calendario?start=2025-01-01');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end']);
    }

    public function test_index_validates_date_format(): void
    {
        $response = $this->getJson('/eventos/calendario?start=invalid-date&end=2025-01-31');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start']);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=not-a-date');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end']);
    }

    public function test_index_returns_empty_array_when_no_events(): void
    {
        EventModel::factory()->published()->create([
            'start_date' => '2024-12-15 10:00:00',
            'end_date' => '2024-12-15 18:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonCount(0);
        $response->assertJson([]);
    }
}
