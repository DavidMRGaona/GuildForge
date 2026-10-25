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
 * A site that held data before the storage zone setting existed stores Madrid wall times,
 * whatever APP_STORAGE_TIMEZONE_DEFAULT says later: the migration records that explicitly.
 */
final class RecordStorageTimezoneForExistingSitesTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    private const string MIGRATION = 'migrations/2026_10_25_000001_record_storage_timezone_for_existing_sites.php';

    public function test_a_freshly_migrated_database_has_no_marker(): void
    {
        // The test databases (and fresh installs) are migrated without users: the env default applies
        $this->assertNull($this->marker());
    }

    public function test_a_site_with_data_gets_a_madrid_marker(): void
    {
        UserModel::factory()->create();

        $this->runMigration('up');

        $this->assertSame('Europe/Madrid', $this->marker());
        $row = DB::table('settings')->where('key', StorageTimezoneResolver::SETTING_KEY)->first();
        $this->assertNotNull($row);
        $this->assertTrue(Str::isUuid((string) $row->id));
        $this->assertNotNull($row->created_at);
    }

    public function test_a_site_without_data_gets_no_marker(): void
    {
        $this->runMigration('up');

        $this->assertNull($this->marker());
    }

    public function test_an_existing_marker_is_kept(): void
    {
        UserModel::factory()->create();
        $this->insertMarker('UTC');

        $this->runMigration('up');

        $this->assertSame('UTC', $this->marker());
        $this->assertSame(1, DB::table('settings')->where('key', StorageTimezoneResolver::SETTING_KEY)->count());
    }

    public function test_down_keeps_the_madrid_marker(): void
    {
        UserModel::factory()->create();
        $this->runMigration('up');

        $this->runMigration('down');

        // Removing a marker is never safe: a UTC environment default would misread Madrid data
        $this->assertSame('Europe/Madrid', $this->marker());
    }

    public function test_down_keeps_a_utc_marker(): void
    {
        // A converted site: removing its marker would read its UTC data as Madrid
        $this->insertMarker('UTC');

        $this->runMigration('down');

        $this->assertSame('UTC', $this->marker());
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
