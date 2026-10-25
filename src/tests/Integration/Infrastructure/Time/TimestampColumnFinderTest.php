<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Time;

use App\Infrastructure\Time\TimestampColumnFinder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TimestampColumnFinderTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_core_instant_columns_and_skips_dates(): void
    {
        $columns = app(TimestampColumnFinder::class)->find();

        $this->assertContains(['table' => 'events', 'column' => 'start_date'], $columns);
        $this->assertContains(['table' => 'users', 'column' => 'created_at'], $columns);
        $this->assertContains(['table' => 'settings', 'column' => 'updated_at'], $columns);
        // useCurrent() in its migration, but Laravel writes it from PHP in the storage zone
        $this->assertContains(['table' => 'failed_jobs', 'column' => 'failed_at'], $columns);
        $this->assertNotContains(['table' => 'ses_usage_records', 'column' => 'date'], $columns);
    }

    public function test_every_database_defaulted_core_timestamp_is_known_to_be_written_by_the_app(): void
    {
        // Fails when a migration adds a useCurrent() (or any database default) timestamp: decide
        // whether the app always writes it (then list it) or the database fills it (then it is
        // not in the storage zone and the conversion must not shift it)
        $this->assertEqualsCanonicalizing(
            TimestampColumnFinder::APP_WRITTEN_DESPITE_DEFAULT,
            app(TimestampColumnFinder::class)->databaseDefaultedColumns(),
        );
    }

    public function test_reports_a_timestamp_the_database_fills_in_any_table(): void
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

    public function test_finds_columns_of_any_table_in_the_schema_and_skips_dates_times_and_json(): void
    {
        // Stands for a module table: the finder reads the schema, not a list of core tables
        Schema::create('zz_module_fixture', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->dateTime('starts_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->date('day');
            $table->time('opens_at');
            $table->json('payload');
        });

        $columns = array_values(array_filter(
            app(TimestampColumnFinder::class)->find(),
            static fn (array $column): bool => $column['table'] === 'zz_module_fixture',
        ));

        $this->assertSame([
            ['table' => 'zz_module_fixture', 'column' => 'reviewed_at'],
            ['table' => 'zz_module_fixture', 'column' => 'starts_at'],
        ], $columns);
    }

    public function test_the_columns_come_sorted_by_table_and_column(): void
    {
        $columns = app(TimestampColumnFinder::class)->find();
        $sorted = $columns;
        usort($sorted, static fn (array $a, array $b): int => [$a['table'], $a['column']] <=> [$b['table'], $b['column']]);

        $this->assertNotEmpty($columns);
        $this->assertSame($sorted, $columns);
    }
}
