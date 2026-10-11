<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class EventResourceTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_single_day_event_shows_date_once_with_start_and_end_times(): void
    {
        $event = EventModel::factory()->create([
            'start_date' => '2026-01-15 18:00:00',
            'end_date' => '2026-01-15 22:00:00',
        ]);

        Livewire::test(ListEvents::class)
            ->assertTableColumnFormattedStateSet('start_date', '15/01/2026 18:00 – 22:00', $event);
    }

    public function test_multi_day_event_shows_start_and_end_date_with_times(): void
    {
        $event = EventModel::factory()->create([
            'start_date' => '2026-01-15 18:00:00',
            'end_date' => '2026-01-17 14:00:00',
        ]);

        Livewire::test(ListEvents::class)
            ->assertTableColumnFormattedStateSet('start_date', '15/01/2026 18:00 – 17/01/2026 14:00', $event);
    }
}
