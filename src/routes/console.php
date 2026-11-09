<?php

declare(strict_types=1);

use App\Infrastructure\Updates\Jobs\CheckModuleUpdatesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Detect new module releases daily so the admin panel shows them without a manual check
Schedule::job(new CheckModuleUpdatesJob)
    ->dailyAt('04:00')
    ->name(CheckModuleUpdatesJob::class)
    ->withoutOverlapping();

// A failed queued notification keeps its payload (recipient, links with tokens): drop it after 14 days
Schedule::command('queue:prune-failed --hours=336')
    ->daily()
    ->withoutOverlapping();

// Anonymous help centre metrics only matter for a while: drop old votes and failed searches
Schedule::command('knowledge:prune-metrics')
    ->daily()
    ->withoutOverlapping();

// Anonymous site search metrics only matter for a while: drop old searches without results
Schedule::command('search:prune-metrics')
    ->daily()
    ->withoutOverlapping();

// Rebuild the help catalog and its search index daily, so an index left stale by a search engine outage heals on its own
Schedule::command('knowledge:sync')
    ->daily()
    ->withoutOverlapping();

// Rebuild the site search documents daily: picks up bulk changes the observers miss and heals an index left stale by an outage
Schedule::command('search:sync')
    ->daily()
    ->withoutOverlapping();

// Daily mail digests (core#333): asked every hour, each run sends what was kept before the last
// digest hour of the display zone, so a missed run is caught up and a repeated one sends nothing
Schedule::command('mail:send-digests')
    ->hourlyAt(5)
    ->withoutOverlapping();
