<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Time;

use App\Domain\Exceptions\InvalidDisplayTimezoneException;
use App\Domain\Exceptions\StorageTimezoneUnavailableException;
use App\Infrastructure\Time\StorageTimezoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class StorageTimezoneResolverTest extends TestCase
{
    use RefreshDatabase;
    use UsesUtcStorage;

    private ?string $transactedConnection = null;

    private string $zoneBeforeTest = 'Europe/Madrid';

    protected function setUp(): void
    {
        parent::setUp();

        $this->zoneBeforeTest = date_default_timezone_get();

        // Pinned so these tests hold when the suite runs with another default (Madrid lane).
        config(['app.storage_timezone_default' => 'Europe/Madrid']);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zoneBeforeTest);

        parent::tearDown();
    }

    public function test_without_a_marker_the_storage_zone_is_the_configured_default(): void
    {
        $this->assertSame('Europe/Madrid', app(StorageTimezoneResolver::class)->resolve());
    }

    public function test_the_marker_sets_the_storage_zone(): void
    {
        DB::table('settings')->insert(['id' => (string) Str::uuid(), 'key' => 'storage_timezone', 'value' => 'UTC']);
        $resolver = app(StorageTimezoneResolver::class);
        $resolver->apply($this->app);

        $this->assertSame('UTC', $resolver->resolve());
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
    }

    public function test_an_unknown_marker_value_fails_closed(): void
    {
        DB::table('settings')->insert(['id' => (string) Str::uuid(), 'key' => 'storage_timezone', 'value' => 'Europe/Paris']);
        $this->expectException(StorageTimezoneUnavailableException::class);
        app(StorageTimezoneResolver::class)->resolve();
    }

    public function test_an_unsupported_default_fails_closed(): void
    {
        config(['app.storage_timezone_default' => 'Europe/Paris']);
        $this->expectException(StorageTimezoneUnavailableException::class);
        app(StorageTimezoneResolver::class)->resolve();
    }

    public function test_before_the_settings_table_exists_the_default_is_used(): void
    {
        // A database of its own with no tables: dropping the table inside the test's transaction
        // would abort it on PostgreSQL, and a fresh install never has that transaction around it
        config([
            'database.connections.without_tables' => ['driver' => 'sqlite', 'database' => ':memory:'],
            'database.default' => 'without_tables',
        ]);

        $this->assertSame('Europe/Madrid', app(StorageTimezoneResolver::class)->resolve());
    }

    public function test_an_unreachable_database_fails_closed_outside_allowlisted_commands(): void
    {
        $this->makeDatabaseUnreachable();
        $this->expectException(StorageTimezoneUnavailableException::class);
        app(StorageTimezoneResolver::class)->resolve(consoleCommand: 'migrate');
    }

    #[DataProvider('healthPathProvider')]
    public function test_the_health_path_keeps_working_while_the_database_is_unreachable(string $path, bool $survives): void
    {
        $this->makeDatabaseUnreachable();

        if (! $survives) {
            $this->expectException(StorageTimezoneUnavailableException::class);
        }

        $this->assertSame('Europe/Madrid', app(StorageTimezoneResolver::class)->resolve(requestPath: $path));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function healthPathProvider(): array
    {
        return [
            'health' => ['/up', true],
            'health with query' => ['/up?x=1', true],
            'other page' => ['/eventos', false],
            'prefix of another path' => ['/upload', false],
            'nested' => ['/up/more', false],
        ];
    }

    public function test_the_health_path_honours_a_readable_marker(): void
    {
        $this->useUtcStorage();
        $this->assertSame('UTC', app(StorageTimezoneResolver::class)->resolve(requestPath: '/up'));
    }

    public function test_the_health_path_still_rejects_an_invalid_marker(): void
    {
        DB::table('settings')->insert(['id' => (string) Str::uuid(), 'key' => 'storage_timezone', 'value' => 'Europe/Paris']);
        $this->expectException(StorageTimezoneUnavailableException::class);
        app(StorageTimezoneResolver::class)->resolve(requestPath: '/up');
    }

    public function test_an_unreachable_database_uses_the_default_for_build_commands(): void
    {
        $this->makeDatabaseUnreachable();
        $this->assertSame('Europe/Madrid', app(StorageTimezoneResolver::class)->resolve(consoleCommand: 'package:discover'));
    }

    #[DataProvider('moduleCommandProvider')]
    public function test_the_module_generators_run_while_the_database_is_unreachable(string $command, bool $survives): void
    {
        $this->makeDatabaseUnreachable();

        if (! $survives) {
            $this->expectException(StorageTimezoneUnavailableException::class);
        }

        $this->assertSame('Europe/Madrid', app(StorageTimezoneResolver::class)->resolve(consoleCommand: $command));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function moduleCommandProvider(): array
    {
        return [
            'module' => ['module:make', true],
            'entity' => ['module:make-entity', true],
            'notification' => ['module:make-notification', true],
            'migrate' => ['module:migrate', false],
            'enable' => ['module:enable', false],
            'prefix of another command' => ['module:makeover', false],
        ];
    }

    public function test_the_display_zone_comes_from_the_environment_default(): void
    {
        $this->assertSame('Europe/Madrid', config('app.display_timezone'));
        $this->assertSame('Europe/Madrid', config('app.schedule_timezone'));
    }

    public function test_an_invalid_display_zone_fails_closed(): void
    {
        config(['app.display_timezone' => 'Not/AZone']);
        $this->expectException(InvalidDisplayTimezoneException::class);
        app(StorageTimezoneResolver::class)->apply($this->app);
    }

    public function test_a_display_zone_that_is_not_an_iana_identifier_is_rejected(): void
    {
        // PHP accepts abbreviations such as CET as zones, but they are not identifiers
        config(['app.display_timezone' => 'CET']);
        $this->expectException(InvalidDisplayTimezoneException::class);
        app(StorageTimezoneResolver::class)->apply($this->app);
    }

    /**
     * @param  list<string>  $argv
     */
    #[DataProvider('argvProvider')]
    public function test_the_command_is_the_first_argument_that_is_not_an_option(array $argv, ?string $expected): void
    {
        $this->assertSame($expected, StorageTimezoneResolver::commandName($argv));
    }

    /**
     * @return array<string, array{list<string>, string|null}>
     */
    public static function argvProvider(): array
    {
        return [
            'plain' => [['artisan', 'migrate'], 'migrate'],
            'option before' => [['artisan', '--ansi', 'package:discover'], 'package:discover'],
            'phpstan' => [['phpstan', '--memory-limit=2G', 'analyse'], 'analyse'],
            'only options' => [['artisan', '--version'], null],
            'no arguments' => [['artisan'], null],
        ];
    }

    public function test_the_test_trait_switches_storage_to_utc(): void
    {
        $this->useUtcStorage();

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());

        $this->useUtcStorage();
        $this->assertSame(1, DB::table('settings')->where('key', 'storage_timezone')->count());
    }

    /**
     * Named, so the rollback still finds the real connection (SQLite or PostgreSQL, as the run
     * is configured) after a test swaps the default.
     *
     * @return list<string>
     */
    protected function connectionsToTransact(): array
    {
        $this->transactedConnection ??= (string) config('database.default');

        return [$this->transactedConnection];
    }

    private function makeDatabaseUnreachable(): void
    {
        config([
            'database.connections.unreachable' => ['driver' => 'sqlite', 'database' => '/nonexistent/dir/db.sqlite'],
            'database.default' => 'unreachable',
        ]);
    }
}
