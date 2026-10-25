<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Application\Services\SettingsServiceInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

final class RecordEmailVerificationCutoffTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    private const string KEY = 'email_verification_enforced_since';

    public function test_it_records_the_current_time_in_utc_when_the_cutoff_is_missing(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
        // Built from timestamps: a relative "-1 second" is resolved on the zone's wall time, which is
        // ambiguous during the autumn hour the clock repeats
        $before = new DateTimeImmutable('@'.(time() - 1));

        $this->runMigration('up');

        $after = new DateTimeImmutable('@'.(time() + 1));
        $rows = DB::table('settings')->where('key', self::KEY)->get();
        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertNotNull($row);

        $value = (string) $row->value;
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $value);
        $recorded = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        $this->assertInstanceOf(DateTimeImmutable::class, $recorded);
        $this->assertGreaterThanOrEqual($before, $recorded);
        $this->assertLessThanOrEqual($after, $recorded);
        $this->assertIsString($row->id);
        $this->assertNotNull($row->created_at);
    }

    public function test_a_second_run_keeps_the_recorded_cutoff(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
        $this->runMigration('up');
        DB::table('settings')->where('key', self::KEY)->update(['value' => '2026-10-14T10:30:00+00:00']);

        $this->runMigration('up');

        $this->assertSame(1, DB::table('settings')->where('key', self::KEY)->count());
        $this->assertSame('2026-10-14T10:30:00+00:00', DB::table('settings')->where('key', self::KEY)->value('value'));
    }

    public function test_the_cutoff_is_visible_through_the_settings_service_right_after_migrating(): void
    {
        $settings = app(SettingsServiceInterface::class);
        DB::table('settings')->where('key', self::KEY)->delete();
        $settings->clearCache();
        $this->assertNull($settings->getEmailVerificationEnforcedSince());

        $this->runMigration('up');

        $this->assertNotNull($settings->getEmailVerificationEnforcedSince());
    }

    public function test_the_settings_cache_is_cleared_only_once_the_migration_commits(): void
    {
        $settings = app(SettingsServiceInterface::class);
        DB::table('settings')->where('key', self::KEY)->delete();
        $settings->clearCache();
        // Warm cache without the cutoff, as on a running site before the deploy
        $this->assertNull($settings->getEmailVerificationEnforcedSince());
        $this->assertTrue(Cache::has('site_settings'));

        // The migrator runs each migration in a transaction on PostgreSQL
        DB::transaction(function (): void {
            $this->runMigration('up');

            // Cleared before the commit, a request could cache the old settings again for an hour
            $this->assertTrue(Cache::has('site_settings'), 'The cache was cleared before the commit');
        });

        $this->assertFalse(Cache::has('site_settings'));
        $this->assertNotNull($settings->getEmailVerificationEnforcedSince());
    }

    public function test_down_keeps_the_cutoff(): void
    {
        $this->runMigration('up');
        $value = DB::table('settings')->where('key', self::KEY)->value('value');

        $this->runMigration('down');

        $this->assertSame($value, DB::table('settings')->where('key', self::KEY)->value('value'));
    }

    private function runMigration(string $direction): void
    {
        $this->runMigrationFile(database_path('migrations/2026_10_14_100003_record_email_verification_cutoff.php'), $direction);
    }
}
