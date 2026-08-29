<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The calendar feed is the boundary the frontend groups entries on, so the
 * serialized offset decides which calendar day an entry lands in.
 */
final class CalendarTimezoneTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_entries_carry_the_summer_time_offset(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Evento de verano',
            'start_date' => '2025-07-15 18:00:00',
            'end_date' => '2025-07-15 21:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-07-01&end=2025-07-31');

        $response->assertStatus(200);
        $response->assertJsonFragment(['start' => '2025-07-15T18:00:00+02:00']);
    }

    public function test_entries_carry_the_winter_time_offset(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Evento de invierno',
            'start_date' => '2025-01-15 18:00:00',
            'end_date' => '2025-01-15 21:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonFragment(['start' => '2025-01-15T18:00:00+01:00']);
    }

    public function test_early_morning_entry_keeps_the_venue_day(): void
    {
        // 00:30 in Madrid is still the previous day in UTC, so an entry serialized
        // without the venue offset would be grouped under 14 July by the frontend.
        EventModel::factory()->published()->create([
            'title' => 'Partida nocturna',
            'start_date' => '2025-07-15 00:30:00',
            'end_date' => '2025-07-15 03:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-07-01&end=2025-07-31');

        $response->assertStatus(200);
        $response->assertJsonFragment(['start' => '2025-07-15T00:30:00+02:00']);
    }
}
