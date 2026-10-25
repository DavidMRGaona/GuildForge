<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

/**
 * Panel values used to be written into each module's config/settings.php; they move to the
 * settings table once, before the next module update replaces those files.
 */
final class MoveModuleSettingsToSettingsTableMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-move-settings-'.bin2hex(random_bytes(8));
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_it_copies_panel_written_settings_files_into_the_settings_table(): void
    {
        $this->module('tournaments', "<?php\n\nreturn ".var_export(['default_check_in_starts_before' => 30, 'nested' => ['on' => false]], true).";\n");
        // A module disabled since the save keeps its values as well
        $this->module('announcements', "<?php\n\nreturn ".var_export(['show_banner' => false], true).";\n", enabled: false);

        $this->runMigrationFile($this->migrationPath());

        $this->assertSame(['default_check_in_starts_before' => 30, 'nested' => ['on' => false]], $this->stored('tournaments'));
        $this->assertSame(['show_banner' => false], $this->stored('announcements'));
    }

    public function test_it_leaves_release_default_files_alone(): void
    {
        $this->module('event-registrations', "<?php\n\nreturn [\n    'send_confirmation_email' => true,\n];\n");

        $this->runMigrationFile($this->migrationPath());

        $this->assertFalse(SettingModel::query()->where('key', 'module_settings:event-registrations')->exists());
    }

    public function test_it_never_overwrites_an_existing_stored_value(): void
    {
        $this->module('venue-bookings', "<?php\n\nreturn ".var_export(['approval_mode' => 'auto'], true).";\n");
        SettingModel::query()->create(['key' => 'module_settings:venue-bookings', 'value' => '{"approval_mode":"require_approval"}']);

        $this->runMigrationFile($this->migrationPath());

        $this->assertSame(['approval_mode' => 'require_approval'], $this->stored('venue-bookings'));
    }

    public function test_it_skips_modules_without_a_settings_file_and_unreadable_files(): void
    {
        $this->module('no-settings', null);
        $this->module('broken', "<?php\n\nreturn array (\n  'a' => \n");
        $this->module('not-an-array', "<?php\n\nreturn array ()[0] ?? 'scalar';\n");
        ModuleModel::factory()->enabled()->create(['name' => 'ghost-mod', 'path' => $this->modulesPath.'/ghost-mod']);

        $this->runMigrationFile($this->migrationPath());

        $this->assertSame(0, SettingModel::query()->where('key', 'like', 'module_settings:%')->count());
    }

    public function test_a_file_whose_values_cannot_be_encoded_is_skipped_without_stopping_the_others(): void
    {
        // A failure here would stop migrate --force and with it the whole deploy
        $this->module('announcements', "<?php\n\nreturn array (\n  'rotate_interval' => INF,\n);\n");
        $this->module('tournaments', "<?php\n\nreturn ".var_export(['default_check_in_starts_before' => 30], true).";\n");

        $this->runMigrationFile($this->migrationPath());

        $this->assertNull($this->stored('announcements'));
        $this->assertSame(['default_check_in_starts_before' => 30], $this->stored('tournaments'));
    }

    public function test_it_can_run_twice(): void
    {
        $this->module('tournaments', "<?php\n\nreturn ".var_export(['default_check_in_starts_before' => 30], true).";\n");

        $this->runMigrationFile($this->migrationPath());
        $this->runMigrationFile($this->migrationPath());

        $this->assertSame(1, SettingModel::query()->where('key', 'module_settings:tournaments')->count());
    }

    private function module(string $name, ?string $settingsFile, bool $enabled = true): void
    {
        File::ensureDirectoryExists("{$this->modulesPath}/{$name}/config");

        if ($settingsFile !== null) {
            File::put("{$this->modulesPath}/{$name}/config/settings.php", $settingsFile);
        }

        $factory = ModuleModel::factory();
        ($enabled ? $factory->enabled() : $factory->disabled())->create(['name' => $name, 'path' => "{$this->modulesPath}/{$name}"]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stored(string $name): ?array
    {
        $value = SettingModel::query()->where('key', "module_settings:{$name}")->value('value');

        return is_string($value) ? json_decode($value, true) : null;
    }

    private function migrationPath(): string
    {
        return database_path('migrations/2026_10_14_000001_move_module_settings_to_settings_table.php');
    }
}
