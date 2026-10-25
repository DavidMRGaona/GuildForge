<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class EventResourceTableTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_single_day_event_shows_date_once_with_start_and_end_times(): void
    {
        $event = EventModel::factory()->create([
            'start_date' => new DateTimeImmutable('2026-01-15 18:00:00', new DateTimeZone('Europe/Madrid')),
            'end_date' => new DateTimeImmutable('2026-01-15 22:00:00', new DateTimeZone('Europe/Madrid')),
        ]);

        Livewire::test(ListEvents::class)
            ->assertTableColumnFormattedStateSet('start_date', '15/01/2026 18:00 – 22:00', $event);
    }

    public function test_multi_day_event_shows_start_and_end_date_with_times(): void
    {
        $event = EventModel::factory()->create([
            'start_date' => new DateTimeImmutable('2026-01-15 18:00:00', new DateTimeZone('Europe/Madrid')),
            'end_date' => new DateTimeImmutable('2026-01-17 14:00:00', new DateTimeZone('Europe/Madrid')),
        ]);

        Livewire::test(ListEvents::class)
            ->assertTableColumnFormattedStateSet('start_date', '15/01/2026 18:00 – 17/01/2026 14:00', $event);
    }

    public function test_an_event_crossing_midnight_in_madrid_shows_both_dates_under_utc_storage(): void
    {
        $this->useUtcStorage();

        // 22:30 and 23:30 UTC are on the same UTC day, but 23:30 and 00:30 Madrid are on different days
        $event = EventModel::factory()->create([
            'start_date' => new DateTimeImmutable('2026-01-15 23:30:00', new DateTimeZone('Europe/Madrid')),
            'end_date' => new DateTimeImmutable('2026-01-16 00:30:00', new DateTimeZone('Europe/Madrid')),
        ]);

        Livewire::test(ListEvents::class)
            ->assertTableColumnFormattedStateSet('start_date', '15/01/2026 23:30 – 16/01/2026 00:30', $event);
    }

    public function test_an_event_on_one_madrid_day_shows_the_date_once_under_utc_storage(): void
    {
        $this->useUtcStorage();

        // 00:10 Madrid on the 16th is the 15th in UTC while 01:30 is the 16th: the display day is what counts
        $event = EventModel::factory()->create([
            'start_date' => new DateTimeImmutable('2026-01-16 00:10:00', new DateTimeZone('Europe/Madrid')),
            'end_date' => new DateTimeImmutable('2026-01-16 01:30:00', new DateTimeZone('Europe/Madrid')),
        ]);

        Livewire::test(ListEvents::class)
            ->assertTableColumnFormattedStateSet('start_date', '16/01/2026 00:10 – 01:30', $event);
    }
}
