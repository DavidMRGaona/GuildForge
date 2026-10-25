<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Time;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Time\StorageTimezoneConverter;
use App\Infrastructure\Time\StorageTimezoneResolver;
use App\Infrastructure\Time\TimestampColumnFinder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The conversion is PostgreSQL SQL (AT TIME ZONE), so it only runs against PostgreSQL:
 * DB_CONNECTION=pgsql with a throwaway database.
 */
#[Group('pgsql')]
final class StorageTimezoneConverterPgsqlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Needs PostgreSQL (DB_CONNECTION=pgsql).');
        }

        config(['app.storage_timezone_default' => 'Europe/Madrid']);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set('Europe/Madrid');

        parent::tearDown();
    }

    public function test_the_command_converts_the_data_and_writes_the_marker_then_reverses_both(): void
    {
        $event = $this->eventStartingAt('2026-07-01 12:00:00');
        $modules = storage_path('framework/testing/convert-storage-pgsql-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($modules);
        config(['modules.path' => $modules]);

        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');

        try {
            $this->artisan('timezone:convert-storage')->assertSuccessful();

            $this->assertSame('2026-07-01 10:00:00', $this->startOf($event));
            $this->assertSame('UTC', DB::table('settings')->where('key', StorageTimezoneResolver::SETTING_KEY)->value('value'));

            $this->artisan('timezone:convert-storage')->expectsOutputToContain('already')->assertSuccessful();
            $this->assertSame('2026-07-01 10:00:00', $this->startOf($event));

            $this->artisan('timezone:convert-storage --reverse')->assertSuccessful();

            $this->assertSame('2026-07-01 12:00:00', $this->startOf($event));
            $this->assertSame('Europe/Madrid', DB::table('settings')->where('key', StorageTimezoneResolver::SETTING_KEY)->value('value'));
        } finally {
            File::deleteDirectory($modules);
        }
    }

    public function test_every_database_defaulted_core_timestamp_is_known_to_be_written_by_the_app_on_postgresql(): void
    {
        $this->assertEqualsCanonicalizing(
            TimestampColumnFinder::APP_WRITTEN_DESPITE_DEFAULT,
            app(TimestampColumnFinder::class)->databaseDefaultedColumns(),
        );
        $this->assertContains(['table' => 'events', 'column' => 'start_date'], app(TimestampColumnFinder::class)->find());
    }

    public function test_reports_a_timestamp_the_database_fills_on_postgresql(): void
    {
        Schema::create('zz_module_fixture', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
        });

        $defaulted = app(TimestampColumnFinder::class)->databaseDefaultedColumns();

        $this->assertContains(['table' => 'zz_module_fixture', 'column' => 'recorded_at'], $defaulted);
        $this->assertNotContains(['table' => 'zz_module_fixture', 'column' => 'reviewed_at'], $defaulted);
    }

    public function test_the_command_lists_zone_aware_columns_without_converting_them(): void
    {
        Schema::create('zz_module_fixture', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestampTz('signed_at');
        });
        DB::statement("insert into zz_module_fixture (id, signed_at) values ('".Str::uuid()."', '2026-07-01 12:00:00+02')");

        $this->artisan('timezone:convert-storage --dry-run')
            ->expectsOutputToContain('Already zone-aware, not converted: zz_module_fixture.signed_at')
            ->assertSuccessful();

        $this->assertNotContains(['table' => 'zz_module_fixture', 'column' => 'signed_at'], app(TimestampColumnFinder::class)->find());
        $this->assertSame([['table' => 'zz_module_fixture', 'column' => 'signed_at']], array_values(array_filter(
            app(TimestampColumnFinder::class)->zoneAware(),
            static fn (array $column): bool => $column['table'] === 'zz_module_fixture',
        )));
    }

    public function test_converts_madrid_wall_times_to_utc_and_back(): void
    {
        $summer = $this->eventStartingAt('2026-07-01 12:00:00');
        $ambiguous = $this->eventStartingAt('2026-10-25 02:10:00');
        $skipped = $this->eventStartingAt('2026-03-29 02:30:00');
        $converter = app(StorageTimezoneConverter::class);

        $counts = DB::transaction(fn (): array => $converter->convert('Europe/Madrid', 'UTC'));

        $this->assertSame(3, $counts['events.start_date']);
        $this->assertSame('2026-07-01 10:00:00', $this->startOf($summer));
        $this->assertSame('2026-10-25 01:10:00', $this->startOf($ambiguous));
        $this->assertSame('2026-03-29 01:30:00', $this->startOf($skipped));

        DB::transaction(fn (): array => $converter->convert('UTC', 'Europe/Madrid'));

        $this->assertSame('2026-07-01 12:00:00', $this->startOf($summer));
        $this->assertSame('2026-10-25 02:10:00', $this->startOf($ambiguous));
        // 02:30 never happened that night in Madrid: it was read as 03:30, so it cannot round-trip
        $this->assertSame('2026-03-29 03:30:00', $this->startOf($skipped));
    }

    public function test_converts_failed_at_and_leaves_nulls_dates_times_and_json_alone(): void
    {
        Schema::create('zz_module_fixture', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamp('starts_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->date('day');
            $table->time('opens_at');
            $table->json('payload');
        });
        DB::table('zz_module_fixture')->insert([
            'id' => (string) Str::uuid(),
            'starts_at' => '2026-07-01 12:00:00',
            'reviewed_at' => null,
            'day' => '2026-07-01',
            'opens_at' => '12:00:00',
            'payload' => json_encode(['at' => '2026-07-01 12:00:00']),
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Exception',
            'failed_at' => '2026-07-01 12:00:00',
        ]);

        $counts = DB::transaction(fn (): array => app(StorageTimezoneConverter::class)->convert('Europe/Madrid', 'UTC'));

        $row = DB::table('zz_module_fixture')->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-07-01 10:00:00', $row->starts_at);
        $this->assertNull($row->reviewed_at);
        $this->assertSame('2026-07-01', $row->day);
        $this->assertSame('12:00:00', $row->opens_at);
        $this->assertSame(['at' => '2026-07-01 12:00:00'], json_decode((string) $row->payload, true));
        // Laravel writes failed_at from PHP in the storage zone, never through its useCurrent() default
        $this->assertSame('2026-07-01 10:00:00', DB::table('failed_jobs')->value('failed_at'));
        $this->assertSame(1, $counts['zz_module_fixture.starts_at']);
        $this->assertSame(0, $counts['zz_module_fixture.reviewed_at']);
        $this->assertSame(1, $counts['failed_jobs.failed_at']);
    }

    public function test_counts_the_values_a_conversion_would_change_without_changing_them(): void
    {
        $event = $this->eventStartingAt('2026-07-01 12:00:00');

        $counts = app(StorageTimezoneConverter::class)->countValues();

        $this->assertSame(1, $counts['events.start_date']);
        $this->assertSame('2026-07-01 12:00:00', $this->startOf($event));
    }

    private function eventStartingAt(string $wallTime): string
    {
        $event = EventModel::factory()->create();
        DB::table('events')->where('id', $event->id)->update(['start_date' => $wallTime]);

        return (string) $event->id;
    }

    private function startOf(string $eventId): string
    {
        return (string) DB::table('events')->where('id', $eventId)->value('start_date');
    }
}
