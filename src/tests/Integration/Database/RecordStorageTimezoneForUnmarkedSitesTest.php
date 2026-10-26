<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Time\StorageTimezoneResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

/**
 * Databases installed after the storage zone setting existed were migrated before their first
 * account, so the earlier migration left them without a marker while they stored in the old
 * default (Europe/Madrid) or whatever APP_STORAGE_TIMEZONE_DEFAULT said. Before the default became
 * UTC, this migration writes down the zone they have been storing in.
 */
final class RecordStorageTimezoneForUnmarkedSitesTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    private const string MIGRATION = 'migrations/2026_10_26_000001_record_storage_timezone_for_unmarked_sites.php';

    public function test_a_site_with_data_and_no_variable_gets_the_old_madrid_default(): void
    {
        $this->setVariable(null);
        UserModel::factory()->create();

        $this->runMigration('up');

        $this->assertSame('Europe/Madrid', $this->marker());
        $row = DB::table('settings')->where('key', StorageTimezoneResolver::SETTING_KEY)->first();
        $this->assertNotNull($row);
        $this->assertTrue(Str::isUuid((string) $row->id));
        $this->assertNotNull($row->created_at);
    }

    public function test_a_site_with_data_gets_the_zone_its_variable_names(): void
    {
        $this->setVariable('UTC');
        UserModel::factory()->create();

        $this->runMigration('up');

        $this->assertSame('UTC', $this->marker());
    }

    public function test_a_variable_naming_madrid_is_recorded_as_madrid(): void
    {
        $this->setVariable('Europe/Madrid');
        UserModel::factory()->create();

        $this->runMigration('up');

        $this->assertSame('Europe/Madrid', $this->marker());
    }

    public function test_an_unsupported_variable_writes_nothing(): void
    {
        // The application refuses to boot with it anyway; guessing a zone here would hide that
        $this->setVariable('Europe/Paris');
        UserModel::factory()->create();

        $this->runMigration('up');

        $this->assertNull($this->marker());
    }

    public function test_a_site_without_data_gets_no_marker(): void
    {
        // A fresh install: it follows the default, UTC
        $this->setVariable(null);

        $this->runMigration('up');

        $this->assertNull($this->marker());
    }

    public function test_an_existing_marker_is_kept(): void
    {
        $this->setVariable(null);
        UserModel::factory()->create();
        $this->insertMarker('UTC');

        $this->runMigration('up');

        $this->assertSame('UTC', $this->marker());
        $this->assertSame(1, DB::table('settings')->where('key', StorageTimezoneResolver::SETTING_KEY)->count());
    }

    public function test_down_keeps_the_marker(): void
    {
        $this->setVariable(null);
        UserModel::factory()->create();
        $this->runMigration('up');

        $this->runMigration('down');

        // Removing a marker is never safe: the UTC default would misread Madrid data
        $this->assertSame('Europe/Madrid', $this->marker());
    }

    private function setVariable(?string $value): void
    {
        config(['app.storage_timezone_variable' => $value]);
    }

    private function insertMarker(string $zone): void
    {
        DB::table('settings')->insert([
            'id' => (string) Str::uuid(), 'key' => StorageTimezoneResolver::SETTING_KEY, 'value' => $zone,
        ]);
    }

    private function marker(): ?string
    {
        $value = DB::table('settings')->where('key', StorageTimezoneResolver::SETTING_KEY)->value('value');

        return $value === null ? null : (string) $value;
    }

    private function runMigration(string $direction): void
    {
        $this->runMigrationFile(database_path(self::MIGRATION), $direction);
    }
}
