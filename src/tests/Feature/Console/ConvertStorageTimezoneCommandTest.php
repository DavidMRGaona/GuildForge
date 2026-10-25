<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\StorageTimezoneConverterInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Time\StorageTimezoneResolver;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\Time\FakeStorageTimezoneConverter;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class ConvertStorageTimezoneCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesUtcStorage;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned so these tests hold when the suite runs with another default (UTC rehearsal)
        config(['app.storage_timezone_default' => 'Europe/Madrid']);

        // A folder of this test only, so modules on the developer's tree never count
        $this->modulesPath = storage_path('framework/testing/convert-storage-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($this->modulesPath);
        config(['modules.path' => $this->modulesPath]);

        // A real run needs it; the tests about maintenance turn it off
        $this->setMaintenance(true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        date_default_timezone_set('Europe/Madrid');

        parent::tearDown();
    }

    public function test_dry_run_lists_columns_without_changing_anything(): void
    {
        $event = EventModel::factory()->create();
        $start = DB::table('events')->where('id', $event->id)->value('start_date');

        $this->artisan('timezone:convert-storage --dry-run')
            ->expectsOutputToContain('events.start_date')
            ->expectsOutputToContain('nothing was changed')
            ->assertSuccessful();

        $this->assertNull($this->marker());
        $this->assertSame($start, DB::table('events')->where('id', $event->id)->value('start_date'));
    }

    public function test_dry_run_says_to_stop_the_queue_worker_and_the_scheduler(): void
    {
        $this->artisan('timezone:convert-storage --dry-run')
            ->expectsOutputToContain('Stop the queue worker and the scheduler')
            ->assertSuccessful();
    }

    public function test_a_real_run_says_to_stop_the_queue_worker_and_the_scheduler(): void
    {
        // Processes that booted before the conversion keep their zone: the command cannot stop them
        $this->fakeConverter();

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('Stop the queue worker and the scheduler')
            ->assertSuccessful();
    }

    public function test_dry_run_warns_about_what_would_stop_a_real_run(): void
    {
        $this->installModule('legacy-module', ['name' => 'legacy-module', 'version' => '1.0.0']);
        $this->createTableWithDatabaseDefaultedTimestamp();
        $this->setMaintenance(false);

        $this->artisan('timezone:convert-storage --dry-run')
            ->expectsOutputToContain('PostgreSQL')
            ->expectsOutputToContain('legacy-module')
            ->expectsOutputToContain('Maintenance mode is off')
            ->expectsOutputToContain('- zz_module_fixture.recorded_at')
            ->assertSuccessful();

        $this->assertNull($this->marker());
    }

    public function test_refuses_on_a_database_other_than_postgresql(): void
    {
        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('PostgreSQL')
            ->assertFailed();

        $this->assertNull($this->marker());
    }

    public function test_refuses_while_an_installed_module_is_not_ready(): void
    {
        $converter = $this->fakeConverter();
        $this->installModule('ready-module', ['name' => 'ready-module', 'version' => '1.0.0', 'storesUtc' => true]);
        $this->installModule('legacy-module', ['name' => 'legacy-module', 'version' => '1.0.0']);
        $this->installModule('flagged-false', ['name' => 'flagged-false', 'version' => '1.0.0', 'storesUtc' => false]);
        File::ensureDirectoryExists($this->modulesPath.'/broken-module');
        File::put($this->modulesPath.'/broken-module/module.json', '{not json');
        // The updater's staging copies are not installed modules
        File::ensureDirectoryExists($this->modulesPath.'/.staging-old');

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('legacy-module')
            ->expectsOutputToContain('flagged-false')
            ->expectsOutputToContain('broken-module (module.json missing or unreadable)')
            ->doesntExpectOutputToContain('ready-module')
            ->doesntExpectOutputToContain('legacy-module (module.json')
            ->doesntExpectOutputToContain('.staging-old')
            ->assertFailed();

        $this->assertSame([], $converter->calls);
        $this->assertNull($this->marker());
    }

    public function test_refuses_outside_maintenance_mode(): void
    {
        $converter = $this->fakeConverter();
        $this->setMaintenance(false);

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('Maintenance mode is off')
            ->assertFailed();

        $this->assertSame([], $converter->calls);
        $this->assertNull($this->marker());
    }

    public function test_reverse_also_refuses_outside_maintenance_mode(): void
    {
        $this->useUtcStorage();
        $converter = $this->fakeConverter();
        $this->setMaintenance(false);

        $this->artisan('timezone:convert-storage --reverse')
            ->expectsOutputToContain('Maintenance mode is off')
            ->assertFailed();

        $this->assertSame([], $converter->calls);
        $this->assertSame('UTC', $this->marker());
    }

    public function test_refuses_while_a_timestamp_is_filled_by_the_database(): void
    {
        // A module column with useCurrent(): the database fills it, so it may not be in the storage zone
        $converter = $this->fakeConverter();
        $this->createTableWithDatabaseDefaultedTimestamp();

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('- zz_module_fixture.recorded_at')
            ->doesntExpectOutputToContain('failed_jobs.failed_at')
            ->assertFailed();

        $this->assertSame([], $converter->calls);
        $this->assertNull($this->marker());
    }

    public function test_refuses_while_jobs_are_queued(): void
    {
        $converter = $this->fakeConverter();
        config(['queue.default' => 'database']);
        Queue::connection('database')->pushRaw('{"job":"Illuminate\\\\Queue\\\\CallQueuedHandler@call"}');

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('1 job(s)')
            ->assertFailed();

        $this->assertSame([], $converter->calls);
        $this->assertNull($this->marker());
    }

    public function test_does_nothing_when_storage_is_already_utc(): void
    {
        $converter = $this->fakeConverter();
        $this->useUtcStorage();

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('already')
            ->assertSuccessful();

        $this->assertSame([], $converter->calls);
        $this->assertSame('UTC', $this->marker());
    }

    public function test_reverse_does_nothing_when_storage_is_madrid(): void
    {
        $converter = $this->fakeConverter();

        $this->artisan('timezone:convert-storage --reverse')
            ->expectsOutputToContain('nothing to undo')
            ->assertSuccessful();

        $this->assertSame([], $converter->calls);
        $this->assertNull($this->marker());
    }

    public function test_converts_writes_the_marker_and_restarts_the_workers(): void
    {
        $converter = $this->fakeConverter(['events.start_date' => 3, 'users.created_at' => 2]);

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('5 value(s) in 2 column(s)')
            ->expectsOutputToContain('events.start_date')
            ->assertSuccessful();

        $this->assertSame([['Europe/Madrid', 'UTC', 1]], $converter->calls);
        $this->assertSame('UTC', $this->marker());
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertNotNull(Cache::get('illuminate:queue:restart'));
    }

    public function test_says_the_conversion_is_committed_when_a_step_after_it_fails(): void
    {
        $this->fakeConverter(['events.start_date' => 1]);
        // The real settings service stays in place (so new settings calls cannot break this test
        // silently); only the last step after the commit fails.
        Artisan::command('queue:restart', function (): void {
            throw new RuntimeException('cache store unreachable');
        });

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('cache store unreachable')
            ->expectsOutputToContain('queue:restart')
            ->expectsOutputToContain('start the queue worker and the scheduler again')
            ->assertFailed();

        $this->assertSame('UTC', $this->marker());
    }

    public function test_the_new_marker_row_carries_utc_timestamps(): void
    {
        $this->fakeConverter();
        $this->travelTo('2026-07-01 10:00:00 UTC');

        $this->artisan('timezone:convert-storage')->assertSuccessful();

        $row = DB::table('settings')->where('key', StorageTimezoneResolver::SETTING_KEY)->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-07-01 10:00:00', $row->created_at);
        $this->assertSame('2026-07-01 10:00:00', $row->updated_at);
    }

    public function test_reverse_converts_back_and_writes_a_madrid_marker_even_with_modules_not_ready(): void
    {
        $this->useUtcStorage();
        $converter = $this->fakeConverter(['events.start_date' => 1]);
        $this->installModule('legacy-module', ['name' => 'legacy-module', 'version' => '1.0.0']);

        $this->artisan('timezone:convert-storage --reverse')->assertSuccessful();

        $this->assertSame([['UTC', 'Europe/Madrid', 1]], $converter->calls);
        // Never removed: without it the zone would depend on APP_STORAGE_TIMEZONE_DEFAULT again
        $this->assertSame('Europe/Madrid', $this->marker());
        $this->assertSame('Europe/Madrid', config('app.timezone'));
    }

    public function test_reverse_writes_a_madrid_marker_when_the_default_zone_is_utc(): void
    {
        // A site born in UTC has no marker to delete: removing it would leave storage in UTC
        config(['app.storage_timezone_default' => 'UTC']);
        $converter = $this->fakeConverter();

        $this->artisan('timezone:convert-storage --reverse')->assertSuccessful();

        $this->assertSame([['UTC', 'Europe/Madrid', 1]], $converter->calls);
        $this->assertSame('Europe/Madrid', $this->marker());
    }

    public function test_nothing_is_rewritten_when_another_run_converted_while_waiting_for_the_lock(): void
    {
        $converter = $this->fakeConverter(onLock: function (): void {
            DB::table('settings')->insert([
                'id' => (string) Str::uuid(), 'key' => StorageTimezoneResolver::SETTING_KEY, 'value' => 'UTC',
            ]);
        });

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('rolled back')
            ->assertFailed();

        // The rewrite never started
        $this->assertSame([], $converter->calls);
        $this->assertNull($this->marker());
    }

    public function test_maintenance_toggled_straight_in_the_database_is_honoured(): void
    {
        $converter = $this->fakeConverter();
        // Warm the settings cache with maintenance on, then switch it off behind the cache's back
        app(SettingsServiceInterface::class)->isMaintenanceModeEnabled();
        DB::table('settings')->where('key', 'maintenance_enabled')->update(['value' => '0']);

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('Maintenance mode is off')
            ->assertFailed();

        $this->assertSame([], $converter->calls);
    }

    public function test_the_module_listing_uses_the_manifest_name_not_the_folder(): void
    {
        $this->fakeConverter();
        $this->installModule('folder-name', ['name' => 'manifest-name', 'version' => '1.0.0']);

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('manifest-name')
            ->doesntExpectOutputToContain('folder-name')
            ->assertFailed();
    }

    public function test_rolls_everything_back_when_the_marker_changes_while_converting(): void
    {
        // Another run that committed first: this one must not convert the data a second time
        $this->fakeConverter(onConvert: function (): void {
            DB::table('settings')->insert([
                'id' => (string) Str::uuid(), 'key' => StorageTimezoneResolver::SETTING_KEY, 'value' => 'UTC',
            ]);
            DB::table('settings')->insert(['id' => (string) Str::uuid(), 'key' => 'converted_rows', 'value' => '1']);
        });

        $this->artisan('timezone:convert-storage')
            ->expectsOutputToContain('rolled back')
            ->assertFailed();

        $this->assertNull($this->marker());
        $this->assertNull(DB::table('settings')->where('key', 'converted_rows')->value('value'));
    }

    private function setMaintenance(bool $enabled): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', $enabled ? '1' : '0');
    }

    private function createTableWithDatabaseDefaultedTimestamp(): void
    {
        Schema::create('zz_module_fixture', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamp('recorded_at')->useCurrent();
        });
    }

    private function marker(): ?string
    {
        $value = DB::table('settings')->where('key', StorageTimezoneResolver::SETTING_KEY)->value('value');

        return $value === null ? null : (string) $value;
    }

    /** @param  array<string, mixed>  $manifest */
    private function installModule(string $folder, array $manifest): void
    {
        File::ensureDirectoryExists($this->modulesPath.'/'.$folder);
        $manifest += ['namespace' => 'Modules\\Fixture', 'provider' => 'Modules\\Fixture\\FixtureServiceProvider'];
        File::put($this->modulesPath.'/'.$folder.'/module.json', (string) json_encode($manifest));
    }

    /**
     * Stands in for the PostgreSQL converter, so the command runs past the driver check on SQLite.
     *
     * @param  array<string, int>  $counts
     */
    private function fakeConverter(array $counts = [], ?Closure $onConvert = null, ?Closure $onLock = null): FakeStorageTimezoneConverter
    {
        $fake = new FakeStorageTimezoneConverter($counts, $onConvert, $onLock);
        $this->app->instance(StorageTimezoneConverterInterface::class, $fake);

        return $fake;
    }
}
