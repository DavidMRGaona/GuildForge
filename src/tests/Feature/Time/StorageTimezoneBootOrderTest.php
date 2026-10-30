<?php

declare(strict_types=1);

namespace Tests\Feature\Time;

use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Facade;
use PDO;
use Tests\TestCase;

final class StorageTimezoneBootOrderTest extends TestCase
{
    private string $previousZone;

    private string|false $previousEnv;

    private string|false $previousDatabase;

    private string|false $previousConnection;

    private string|false $previousUrl;

    private ?string $databaseFile = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousZone = date_default_timezone_get();
        $this->previousEnv = getenv('APP_STORAGE_TIMEZONE_DEFAULT');
        $this->previousDatabase = getenv('DB_DATABASE');
        $this->previousConnection = getenv('DB_CONNECTION');
        $this->previousUrl = getenv('DB_URL');
    }

    protected function tearDown(): void
    {
        $this->setStorageDefault($this->previousEnv === false ? null : $this->previousEnv);
        $this->setEnv('DB_DATABASE', $this->previousDatabase === false ? null : $this->previousDatabase);
        $this->setEnv('DB_CONNECTION', $this->previousConnection === false ? null : $this->previousConnection);
        $this->setEnv('DB_URL', $this->previousUrl === false ? null : $this->previousUrl);
        date_default_timezone_set($this->previousZone);

        if ($this->databaseFile !== null) {
            @unlink($this->databaseFile);
        }

        // Requiring bootstrap/app.php builds a second application that replaces the global
        // container and facade root; put the test's own back before the next test runs
        Container::setInstance($this->app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);

        parent::tearDown();
    }

    public function test_the_storage_zone_is_active_before_any_provider_registers(): void
    {
        $this->setStorageDefault('UTC');
        date_default_timezone_set('Asia/Tokyo');

        $app = require base_path('bootstrap/app.php');

        $zoneWhenProvidersStart = null;
        $app->beforeBootstrapping(RegisterProviders::class, function () use (&$zoneWhenProvidersStart): void {
            $zoneWhenProvidersStart = date_default_timezone_get();
        });

        $app->make(Kernel::class)->bootstrap();

        $this->assertSame('UTC', $zoneWhenProvidersStart);
    }

    public function test_a_marker_row_is_read_when_the_hook_runs(): void
    {
        // The marker says UTC while the default says Madrid: only reading the row gives UTC
        $this->setStorageDefault('Europe/Madrid');
        $this->databaseFile = sys_get_temp_dir().'/gf-marker-'.bin2hex(random_bytes(8)).'.sqlite';
        $pdo = new PDO('sqlite:'.$this->databaseFile);
        $pdo->exec('CREATE TABLE settings (id TEXT PRIMARY KEY, "key" TEXT, value TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO settings (id, \"key\", value) VALUES ('1', 'storage_timezone', 'UTC')");
        unset($pdo);
        // The application built below reads its connection from the environment: select SQLite
        // explicitly, whatever driver the surrounding run uses (CI runs the suite on PostgreSQL)
        $this->setEnv('DB_CONNECTION', 'sqlite');
        $this->setEnv('DB_URL', null);
        $this->setEnv('DB_DATABASE', $this->databaseFile);
        date_default_timezone_set('Asia/Tokyo');

        $app = require base_path('bootstrap/app.php');

        $zoneInsideProvider = null;
        $app->beforeBootstrapping(RegisterProviders::class, function () use (&$zoneInsideProvider): void {
            $zoneInsideProvider = date_default_timezone_get();
        });

        $app->make(Kernel::class)->bootstrap();

        $this->assertSame('UTC', $zoneInsideProvider);
        $this->assertSame('UTC', $app->make('config')->get('app.timezone'));
    }

    private function setStorageDefault(?string $zone): void
    {
        $this->setEnv('APP_STORAGE_TIMEZONE_DEFAULT', $zone);
    }

    private function setEnv(string $name, ?string $value): void
    {
        foreach (['_ENV', '_SERVER'] as $bag) {
            if ($value === null) {
                unset($GLOBALS[$bag][$name]);
            } else {
                $GLOBALS[$bag][$name] = $value;
            }
        }

        putenv($value === null ? $name : "{$name}={$value}");
    }
}
