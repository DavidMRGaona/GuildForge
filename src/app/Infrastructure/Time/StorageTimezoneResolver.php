<?php

declare(strict_types=1);

namespace App\Infrastructure\Time;

use App\Domain\Exceptions\InvalidDisplayTimezoneException;
use App\Domain\Exceptions\StorageTimezoneUnavailableException;
use DateTimeZone;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * Decides the zone dates are stored in (app.timezone).
 *
 * The zone lives in the database (settings row "storage_timezone"), not in the
 * environment: it must change together with the stored data. Without the row the
 * configured default applies. Read straight from the table, never through the
 * settings service, whose cache could be stale. Fails closed: guessing the zone
 * would silently shift every stored date by an hour or two.
 */
final class StorageTimezoneResolver
{
    public const string SETTING_KEY = 'storage_timezone';

    /** @var list<string> */
    public const array ALLOWED = ['Europe/Madrid', 'UTC'];

    /**
     * Commands that run without a database (image build, key setup) and never touch dates.
     * "test" only spawns PHPUnit, which boots its own app on the testing database; "analyse"
     * is PHPStan booting the application for static analysis.
     *
     * @var list<string>
     */
    private const array BUILD_COMMANDS = [
        'package:discover', 'config:cache', 'config:clear', 'view:cache', 'view:clear',
        'route:cache', 'route:clear', 'clear-compiled', 'event:clear', 'icons:clear',
        'filament:optimize-clear', 'vendor:publish', 'filament:upgrade', 'list', 'key:generate', 'test', 'analyse', 'analyze',
    ];

    public function resolve(?string $consoleCommand = null, ?Application $app = null): string
    {
        $app ??= Container::getInstance();

        $default = (string) $app->make('config')->get('app.storage_timezone_default', 'Europe/Madrid');
        $this->assertAllowed($default);

        try {
            $value = $app->make('db')->table('settings')->where('key', self::SETTING_KEY)->value('value');
        } catch (Throwable $e) {
            // The common case is one query; only a failure needs to tell "no table yet" from "no database"
            if ($this->settingsTableIsMissing($app)) {
                return $default;
            }

            if ($consoleCommand !== null && in_array($consoleCommand, self::BUILD_COMMANDS, true)) {
                return $default;
            }

            throw StorageTimezoneUnavailableException::unreadable($e);
        }

        if ($value === null) {
            return $default;
        }

        $zone = (string) $value;
        $this->assertAllowed($zone);

        return $zone;
    }

    public function apply(Application $app): void
    {
        $command = null;

        if ($app->runningInConsole()) {
            $command = self::commandName($_SERVER['argv'] ?? []);
        }

        $zone = $this->resolve($command, $app);

        $config = $app->make('config');
        $display = (string) $config->get('app.display_timezone', 'Europe/Madrid');

        if (! in_array($display, DateTimeZone::listIdentifiers(), true)) {
            throw InvalidDisplayTimezoneException::unknown($display);
        }

        $config->set('app.timezone', $zone);
        date_default_timezone_set($zone);
    }

    /**
     * The first argument after the script that is not an option, so "artisan --ansi
     * package:discover" and "phpstan --memory-limit=2G analyse" are recognised.
     */
    public static function commandName(mixed $argv): ?string
    {
        if (! is_array($argv)) {
            return null;
        }

        foreach (array_slice($argv, 1) as $argument) {
            if (is_string($argument) && $argument !== '' && ! str_starts_with($argument, '-')) {
                return $argument;
            }
        }

        return null;
    }

    private function settingsTableIsMissing(ContainerContract $app): bool
    {
        try {
            return ! $app->make('db')->connection()->getSchemaBuilder()->hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }

    private function assertAllowed(string $zone): void
    {
        if (! in_array($zone, self::ALLOWED, true)) {
            throw StorageTimezoneUnavailableException::unsupported($zone);
        }
    }
}
