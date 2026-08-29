<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EventDateTimezoneTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_application_runs_in_the_venue_timezone(): void
    {
        self::assertSame('Europe/Madrid', config('app.timezone'));
    }

    public function test_start_date_is_serialized_with_the_summer_time_offset(): void
    {
        EventModel::factory()->published()->create([
            'start_date' => '2026-08-29 18:00:00',
            'end_date' => '2026-08-29 21:00:00',
        ]);

        $response = $this->get('/eventos');

        $response->assertInertia(
            fn (Assert $page) => $page
                ->where('events.data.0.startDate', '2026-08-29T18:00:00+02:00')
        );
    }

    public function test_start_date_is_serialized_with_the_winter_time_offset(): void
    {
        EventModel::factory()->published()->create([
            'start_date' => '2026-01-15 18:00:00',
            'end_date' => '2026-01-15 21:00:00',
        ]);

        $response = $this->get('/eventos');

        $response->assertInertia(
            fn (Assert $page) => $page
                ->where('events.data.0.startDate', '2026-01-15T18:00:00+01:00')
        );
    }
}
