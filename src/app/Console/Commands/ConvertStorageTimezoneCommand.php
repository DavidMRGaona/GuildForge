<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\StorageTimezoneConverterInterface;
use App\Infrastructure\Modules\Services\ModuleManifestReader;
use App\Infrastructure\Time\StorageTimezoneResolver;
use App\Infrastructure\Time\TimestampColumnFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Moves the stored instants from Europe/Madrid wall times to UTC (or back with --reverse) and
 * writes the storage zone marker in the same transaction. Run by hand in a maintenance window;
 * it refuses while anything could still write or read dates in the old zone.
 */
final class ConvertStorageTimezoneCommand extends Command
{
    private const string MADRID = 'Europe/Madrid';

    private const string UTC = 'UTC';

    protected $signature = 'timezone:convert-storage
                            {--dry-run : Count the values that would be converted without changing anything}
                            {--reverse : Convert back from UTC to Europe/Madrid}';

    protected $description = 'Convert the stored dates from Europe/Madrid to UTC and record that they are stored in UTC';

    public function handle(
        StorageTimezoneConverterInterface $converter,
        StorageTimezoneResolver $resolver,
        SettingsServiceInterface $settings,
        TimestampColumnFinder $finder,
        ModuleManifestReader $manifestReader,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $reverse = (bool) $this->option('reverse');
        [$from, $to] = $reverse ? [self::UTC, self::MADRID] : [self::MADRID, self::UTC];

        $current = $resolver->resolve();

        if ($current === $to) {
            $this->info($reverse
                ? 'Stored dates are already in Europe/Madrid: nothing to undo.'
                : 'Stored dates are already in UTC: nothing to convert.');

            return self::SUCCESS;
        }

        // Maintenance mode only holds back web visitors: these processes keep the zone they booted with
        $this->warn('Stop the queue worker and the scheduler before converting and start them again once it ends: '
            .'this command cannot stop them, and one started before the conversion keeps writing dates in the old zone. '
            .'Panel users get past maintenance mode too: nobody should edit anything meanwhile.');

        // A dry run reports every blocker a real run would stop at, and still counts
        $blockers = $this->blockers($converter, $settings, $finder, $manifestReader, $reverse);

        $zoneAware = $finder->zoneAware();

        if ($zoneAware !== []) {
            $this->line('Already zone-aware, not converted: '.implode(', ', array_map(
                static fn (array $column): string => $column['table'].'.'.$column['column'],
                $zoneAware,
            )));
        }

        if ($dryRun) {
            $this->reportBlockers($blockers, 'warn');

            $counts = $converter->countValues();
            $this->countsTable($counts);
            $this->info(array_sum($counts).' value(s) in '.count($counts)." column(s) would be converted from {$from} to {$to}. Dry run: nothing was changed.");

            return self::SUCCESS;
        }

        if ($blockers !== []) {
            $this->reportBlockers($blockers, 'error');

            $this->error('Nothing was changed.');

            return self::FAILURE;
        }

        $closureReturned = false;

        try {
            $counts = DB::transaction(function () use ($converter, $resolver, $from, $to, $current, &$closureReturned): array {
                // Re-checked under the conversion's lock, before any value is rewritten: a run that
                // committed first already converted the data
                $counts = $converter->convert($from, $to, function () use ($resolver, $current): void {
                    $this->assertStorageZoneUnchanged($resolver, $current);
                });

                $this->assertStorageZoneUnchanged($resolver, $current);

                $this->writeMarker($to);
                $closureReturned = true;

                return $counts;
            });
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            if ($closureReturned) {
                // Only COMMIT can still fail here: the database may or may not have applied it
                $this->error('The commit did not confirm, so the outcome is unknown: check the storage_timezone setting and a few stored dates before doing anything else.');

                return self::FAILURE;
            }

            $this->error('Every change was rolled back: stored dates are still in '.$current.'.');

            return self::FAILURE;
        }

        $this->countsTable($counts);
        $this->info(array_sum($counts).' value(s) in '.count($counts)." column(s) converted from {$from} to {$to}.");

        try {
            $settings->clearCache();
            $resolver->apply($this->laravel);
            Artisan::call('queue:restart');
        } catch (Throwable $e) {
            // The data and the marker are committed: say so, never let it look like a failed conversion
            $this->error("Dates are now stored in {$to}, but finishing failed: {$e->getMessage()}");
            // Never suggest cache:clear: with Redis it flushes a database the tenants share
            $this->error('Run "php artisan queue:restart" by hand; the cached settings expire within the hour.');
            $this->error('Then start the queue worker and the scheduler again and turn maintenance mode off.');

            return self::FAILURE;
        }

        $this->info("Dates are now stored in {$to}. Start the queue worker and the scheduler again, then turn maintenance mode off.");

        return self::SUCCESS;
    }

    private function assertStorageZoneUnchanged(StorageTimezoneResolver $resolver, string $expected): void
    {
        if ($resolver->resolve() !== $expected) {
            throw new RuntimeException('The storage timezone changed while converting (another run?)');
        }
    }

    /**
     * @return list<array{message: string, details: list<string>}> Why a real run would be refused
     */
    private function blockers(
        StorageTimezoneConverterInterface $converter,
        SettingsServiceInterface $settings,
        TimestampColumnFinder $finder,
        ModuleManifestReader $manifestReader,
        bool $reverse,
    ): array {
        $blockers = [];

        // Settings are cached for an hour: a toggle made straight in the database must be honoured
        $settings->clearCache();

        if (! $settings->isMaintenanceModeEnabled()) {
            $blockers[] = ['message' => 'Maintenance mode is off: turn it on first, so nobody writes dates while they are converted.', 'details' => []];
        }

        if (! $converter->supportsConnection()) {
            $driver = DB::connection()->getDriverName();
            $blockers[] = ['message' => "The conversion only runs on PostgreSQL (this connection uses {$driver}).", 'details' => []];
        }

        // Modules not ready for UTC still write Madrid, so going back to Madrid never waits for them
        if (! $reverse) {
            $notReady = $this->modulesNotReady($manifestReader);

            if ($notReady !== []) {
                $blockers[] = [
                    'message' => 'Installed modules not ready for UTC storage (module.json without "storesUtc": true), update or remove them first:',
                    'details' => $notReady,
                ];
            }
        }

        $filledByDatabase = array_values(array_filter(
            $finder->databaseDefaultedColumns(),
            static fn (array $column): bool => ! in_array($column, TimestampColumnFinder::APP_WRITTEN_DESPITE_DEFAULT, true),
        ));

        if ($filledByDatabase !== []) {
            $blockers[] = [
                'message' => 'Timestamps with a database default, which may not hold storage-zone times; check how each one is written first:',
                'details' => array_map(static fn (array $column): string => $column['table'].'.'.$column['column'], $filledByDatabase),
            ];
        }

        try {
            $queued = Queue::size();

            if ($queued > 0) {
                $blockers[] = ['message' => "{$queued} job(s) are waiting in the queue: let the worker finish them first, their dates are in the old zone.", 'details' => []];
            }
        } catch (Throwable $e) {
            $blockers[] = ['message' => 'Cannot tell whether the queue is empty: '.$e->getMessage(), 'details' => []];
        }

        return $blockers;
    }

    /**
     * Every module folder counts, enabled or not, ready only with "storesUtc": true. A folder whose
     * manifest cannot be read is not ready either.
     *
     * @return list<string>
     */
    private function modulesNotReady(ModuleManifestReader $reader): array
    {
        $path = (string) config('modules.path');

        if (! is_dir($path)) {
            return [];
        }

        $entries = scandir($path);

        if ($entries === false) {
            return ["(cannot list {$path})"];
        }

        $notReady = [];

        foreach ($entries as $entry) {
            // Dot directories are the updater's staging/previous copies, never modules
            if (str_starts_with($entry, '.') || ! is_dir($path.'/'.$entry)) {
                continue;
            }

            try {
                $manifest = $reader->read($path.'/'.$entry.'/module.json');
            } catch (Throwable) {
                $notReady[] = "{$entry} (module.json missing or unreadable)";

                continue;
            }

            if (! $manifest->storesUtc) {
                $notReady[] = $manifest->name;
            }
        }

        sort($notReady);

        return $notReady;
    }

    /**
     * Written directly, with timestamps in the new zone: the settings service would stamp the row
     * in the zone this process booted with. Always written, never removed: without the row the zone
     * would depend on APP_STORAGE_TIMEZONE_DEFAULT again.
     */
    private function writeMarker(string $zone): void
    {
        $key = StorageTimezoneResolver::SETTING_KEY;
        $now = Carbon::now($zone)->toDateTimeString();

        if (DB::table('settings')->where('key', $key)->exists()) {
            DB::table('settings')->where('key', $key)->update(['value' => $zone, 'updated_at' => $now]);
        } else {
            DB::table('settings')->insert([
                'id' => (string) Str::uuid(), 'key' => $key, 'value' => $zone, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    /** @param  list<array{message: string, details: list<string>}>  $blockers */
    private function reportBlockers(array $blockers, string $style): void
    {
        foreach ($blockers as $blocker) {
            $style === 'error' ? $this->error($blocker['message']) : $this->warn($blocker['message']);

            foreach ($blocker['details'] as $detail) {
                $this->line("  - {$detail}");
            }
        }
    }

    /** @param  array<string, int>  $counts */
    private function countsTable(array $counts): void
    {
        $rows = [];

        foreach ($counts as $column => $count) {
            $rows[] = [$column, $count];
        }

        $this->table(['Column', 'Values'], $rows);
    }
}
