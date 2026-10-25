<?php

declare(strict_types=1);

namespace Tests\Feature\Time;

use App\Infrastructure\Updates\Jobs\CheckModuleUpdatesJob;
use DateTimeZone;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

/**
 * Scheduled times and the browser follow the display zone, not the storage zone.
 */
final class ScheduleAndLayoutTimezoneTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_scheduled_tasks_run_on_display_time_not_storage_time(): void
    {
        $this->assertSame('Europe/Madrid', $this->scheduledUpdateCheckTimezone());

        // Storage in UTC must not drag the schedule along: it comes from the
        // schedule_timezone default in config/app.php, not from app.timezone.
        config(['app.timezone' => 'UTC']);

        $this->assertSame('Europe/Madrid', $this->scheduledUpdateCheckTimezone());

        // And the setting is what the scheduler reads.
        config([
            'app.schedule_timezone' => 'Atlantic/Canary',
        ]);

        $this->assertSame('Atlantic/Canary', $this->scheduledUpdateCheckTimezone());
    }

    public function test_the_layout_announces_the_display_timezone(): void
    {
        $this->get('/')->assertSee('<meta name="display-timezone" content="Europe/Madrid">', false);
    }

    /**
     * Rebuilds the schedule from routes/console.php under the current config,
     * because the shared instance and its events are built once at boot.
     *
     * @phpstan-impure
     */
    private function scheduledUpdateCheck(): Event
    {
        $this->app->forgetInstance(Schedule::class);
        Facade::clearResolvedInstance(Schedule::class);

        require base_path('routes/console.php');

        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $e): bool => $e->description === CheckModuleUpdatesJob::class);

        $this->assertInstanceOf(Event::class, $event);

        return $event;
    }

    /**
     * @phpstan-impure
     */
    private function scheduledUpdateCheckTimezone(): string
    {
        $timezone = $this->scheduledUpdateCheck()->timezone;

        return $timezone instanceof DateTimeZone ? $timezone->getName() : $timezone;
    }
}
