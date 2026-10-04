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
}
