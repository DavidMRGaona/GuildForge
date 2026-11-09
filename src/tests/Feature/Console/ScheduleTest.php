<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Infrastructure\Updates\Jobs\CheckModuleUpdatesJob;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

final class ScheduleTest extends TestCase
{
    public function test_module_update_check_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(
            fn (Event $event): bool => $event->description === CheckModuleUpdatesJob::class
                && $event->expression === '0 4 * * *'
        ));
    }

    public function test_failed_jobs_are_pruned_daily_after_14_days(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(
            fn (Event $event): bool => str_contains($event->command ?? '', 'queue:prune-failed --hours=336')
                && $event->expression === '0 0 * * *'
        ));
    }

    public function test_daily_mail_digests_are_asked_for_every_hour(): void
    {
        $events = collect(app(Schedule::class)->events());

        // Hourly, so the digest hour of the display zone is honoured whatever the scheduler zone
        $this->assertTrue($events->contains(
            fn (Event $event): bool => str_contains($event->command ?? '', 'mail:send-digests')
                && $event->expression === '5 * * * *'
        ));
    }
}
