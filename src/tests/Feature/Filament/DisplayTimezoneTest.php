<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class DisplayTimezoneTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_the_event_form_shows_and_saves_display_wall_times(): void
    {
        $this->useUtcStorage();
        $event = EventModel::factory()->create([
            'start_date' => new DateTimeImmutable('2026-07-01T10:00:00Z'),
            'end_date' => new DateTimeImmutable('2026-07-01T14:00:00Z'),
        ]);
        $event->tags()->attach(TagModel::factory()->forEvents()->create());

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            // The raw state is what the picker shows; getState() (assertFormSet) hands back the storage-zone value
            ->assertSet('data.start_date', '2026-07-01 12:00:00')
            ->assertFormSet(['start_date' => '2026-07-01 10:00:00'])
            ->fillForm(['start_date' => '2026-07-01 13:00:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-07-01 11:00:00', DB::table('events')->where('id', $event->id)->value('start_date'));
    }

    public function test_table_columns_show_display_times(): void
    {
        $this->useUtcStorage();
        $article = ArticleModel::factory()->create([
            'published_at' => new DateTimeImmutable('2026-07-01T10:00:00Z'),
        ]);

        Livewire::test(ListArticles::class)
            ->assertTableColumnFormattedStateSet('published_at', '01/07/2026 12:00', $article);
    }

    public function test_the_event_range_column_shows_display_times(): void
    {
        $this->useUtcStorage();
        $event = EventModel::factory()->create([
            'start_date' => new DateTimeImmutable('2026-07-01T10:00:00Z'),
            'end_date' => new DateTimeImmutable('2026-07-01T14:00:00Z'),
        ]);

        Livewire::test(ListEvents::class)
            ->assertTableColumnFormattedStateSet('start_date', '01/07/2026 12:00 – 16:00', $event);
    }

    public function test_text_entries_show_dates_in_the_display_zone(): void
    {
        $this->assertSame('Europe/Madrid', TextEntry::make('created_at')->getTimezone());
    }
}
