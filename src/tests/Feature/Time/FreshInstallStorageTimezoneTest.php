<?php

declare(strict_types=1);

namespace Tests\Feature\Time;

use App\Infrastructure\Time\StorageTimezoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A fresh install has no data to convert, so it starts storing in UTC. Sites that held data
 * before the storage_timezone setting existed keep the Madrid marker their migration wrote.
 */
final class FreshInstallStorageTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private const string VARIABLE = 'APP_STORAGE_TIMEZONE_DEFAULT';

    public function test_without_the_variable_the_storage_default_is_utc(): void
    {
        $this->assertSame('UTC', $this->shippedDefault());
    }

    public function test_the_example_environment_stores_in_utc(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^'.self::VARIABLE.'=UTC$/m', $example);
    }

    public function test_a_freshly_migrated_install_stores_in_utc(): void
    {
        config(['app.storage_timezone_default' => $this->shippedDefault()]);

        $this->assertFalse(DB::table('settings')->where('key', StorageTimezoneResolver::SETTING_KEY)->exists());
        $this->assertSame('UTC', app(StorageTimezoneResolver::class)->resolve());
    }

    /** The value config/app.php falls back to when the environment does not set the variable. */
    private function shippedDefault(): mixed
    {
        $saved = [$_ENV[self::VARIABLE] ?? null, $_SERVER[self::VARIABLE] ?? null, getenv(self::VARIABLE)];
        unset($_ENV[self::VARIABLE], $_SERVER[self::VARIABLE]);
        putenv(self::VARIABLE);

        try {
            return (require config_path('app.php'))['storage_timezone_default'];
        } finally {
            [$env, $server, $process] = $saved;

            if ($env !== null) {
                $_ENV[self::VARIABLE] = $env;
            }

            if ($server !== null) {
                $_SERVER[self::VARIABLE] = $server;
            }

            if ($process !== false) {
                putenv(self::VARIABLE.'='.$process);
            }
        }
    }
}
