<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleSeederHistoryModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

/**
 * Installed modules were seeded before seeders were tracked; without this backfill
 * their seeders would run once more on the next update and overwrite admin edits.
 */
final class BackfillModuleSeederHistoryMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-backfill-'.bin2hex(random_bytes(8));
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_it_marks_the_seeders_of_installed_modules_as_executed(): void
    {
        $this->module('game-tables', 'Modules\\GameTables', installed: true, seeders: ['GameSystemsSeeder', 'PublishersSeeder']);

        $this->runMigrationFile($this->migrationPath());

        $this->assertEqualsCanonicalizing(
            ['Modules\\GameTables\\Database\\Seeders\\GameSystemsSeeder', 'Modules\\GameTables\\Database\\Seeders\\PublishersSeeder'],
            ModuleSeederHistoryModel::getExecutedSeeders('game-tables'),
        );
    }

    public function test_it_leaves_modules_that_were_never_installed_to_be_seeded_on_install(): void
    {
        $this->module('tournaments', 'Modules\\Tournaments', installed: false, seeders: ['GameProfilesSeeder']);

        $this->runMigrationFile($this->migrationPath());

        $this->assertSame([], ModuleSeederHistoryModel::getExecutedSeeders('tournaments'));
    }

    public function test_it_keeps_existing_history_and_can_run_twice(): void
    {
        $this->module('cookie-consent', 'Modules\\CookieConsent', installed: true, seeders: ['DefaultCategoriesSeeder']);
        ModuleSeederHistoryModel::markExecuted('cookie-consent', 'Modules\\CookieConsent\\Database\\Seeders\\DefaultCategoriesSeeder');

        $this->runMigrationFile($this->migrationPath());
        $this->runMigrationFile($this->migrationPath());

        $this->assertCount(1, ModuleSeederHistoryModel::getExecutedSeeders('cookie-consent'));
    }

    public function test_it_skips_modules_whose_files_are_missing(): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => 'ghost-mod', 'path' => $this->modulesPath.'/ghost-mod', 'namespace' => 'Modules\\GhostMod', 'installed_at' => now(),
        ]);

        $this->runMigrationFile($this->migrationPath());

        $this->assertSame([], ModuleSeederHistoryModel::getExecutedSeeders('ghost-mod'));
    }

    /**
     * @param  array<int, string>  $seeders
     */
    private function module(string $name, string $namespace, bool $installed, array $seeders): void
    {
        File::ensureDirectoryExists("{$this->modulesPath}/{$name}/database/seeders");

        foreach ($seeders as $seeder) {
            File::put("{$this->modulesPath}/{$name}/database/seeders/{$seeder}.php", '<?php');
        }

        ModuleModel::factory()->enabled()->create([
            'name' => $name,
            'path' => "{$this->modulesPath}/{$name}",
            'namespace' => $namespace,
            'installed_at' => $installed ? now() : null,
        ]);
    }

    private function migrationPath(): string
    {
        return database_path('migrations/2026_10_04_000001_backfill_module_seeder_history.php');
    }
}
