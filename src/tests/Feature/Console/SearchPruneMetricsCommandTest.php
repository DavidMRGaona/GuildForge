<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Application\Services\TimezoneServiceInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SearchPruneMetricsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_prune_removes_rows_older_than_the_retention(): void
    {
        $this->seedRows();

        $this->artisanCommand('search:prune-metrics')->assertSuccessful();

        $this->assertSame(['new'], DB::table('search_misses')->pluck('normalized_query')->all());
    }

    public function test_days_option_overrides_the_configured_retention(): void
    {
        $this->seedRows();

        $this->artisanCommand('search:prune-metrics', ['--days' => 0])->assertSuccessful();

        $this->assertSame(0, DB::table('search_misses')->count());
    }

    public function test_invalid_days_fail(): void
    {
        $this->artisanCommand('search:prune-metrics', ['--days' => 'abc'])->assertFailed();
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(
            fn (Event $event): bool => str_contains($event->command ?? '', 'search:prune-metrics')
                && $event->expression === '0 0 * * *'
        ));
    }

    private function seedRows(): void
    {
        $today = app(TimezoneServiceInterface::class)->today();
        DB::table('search_misses')->insert([
            ['id' => '00000000-0000-4000-8000-000000000001', 'normalized_query' => 'old', 'day' => $today->modify('-200 days')->format('Y-m-d'), 'count' => 1],
            ['id' => '00000000-0000-4000-8000-000000000002', 'normalized_query' => 'new', 'day' => $today->modify('-1 day')->format('Y-m-d'), 'count' => 1],
        ]);
    }
}
