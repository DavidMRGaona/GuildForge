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
