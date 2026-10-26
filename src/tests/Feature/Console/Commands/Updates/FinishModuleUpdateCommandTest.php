<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Updates\DTOs\HealthCheckResultDTO;
use App\Application\Updates\DTOs\PostUpdateReportDTO;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleSeederHistoryModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FinishModuleUpdateCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-finish-'.bin2hex(random_bytes(8));
        config(['modules.path' => $this->modulesPath, 'updates.behavior.health_check' => true]);
        $this->makeModule();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_it_runs_migrations_and_seeders_that_call_other_seeders(): void
    {
        $this->healthCheck(passes: true);

        $this->finish()->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('finish_mod_items'));
        $this->assertSame(1, DB::table('finish_mod_items')->count());
    }

    public function test_it_records_the_new_version_with_the_database_changes(): void
    {
        $this->healthCheck(passes: true);

        $this->finish()->assertExitCode(0);

        $row = ModuleModel::query()->where('name', 'finish-mod')->firstOrFail();
        $this->assertSame('1.0.1-beta', $row->version);
        $this->assertNull($row->latest_available_version);
    }

    public function test_it_reports_the_migrations_applied_and_the_seeders_run(): void
    {
        $this->healthCheck(passes: true);
        Artisan::call('module:finish-update', ['name' => 'finish-mod', 'version' => '1.0.1-beta']);

        $report = PostUpdateReportDTO::fromOutput(Artisan::output());

        $this->assertNotNull($report);
        $this->assertSame(['2026_10_04_000000_create_finish_mod_items_table'], $report->migrations);
        $this->assertSame(['FinishModDatabaseSeeder', 'ItemsSeeder'], $report->seeders);
    }

    public function test_it_does_not_report_migrations_that_had_already_run(): void
    {
        $this->healthCheck(passes: true);
        Artisan::call('module:finish-update', ['name' => 'finish-mod', 'version' => '1.0.1-beta']);
        Artisan::call('module:finish-update', ['name' => 'finish-mod', 'version' => '1.0.1-beta']);

        $report = PostUpdateReportDTO::fromOutput(Artisan::output());

        $this->assertNotNull($report);
        $this->assertSame([], $report->migrations);
    }

    public function test_seeders_that_already_ran_are_not_run_again(): void
    {
        $this->healthCheck(passes: true);
        Artisan::call('module:finish-update', ['name' => 'finish-mod', 'version' => '1.0.1-beta']);
        DB::table('finish_mod_items')->where('name', 'default')->delete(); // an admin removed it

        Artisan::call('module:finish-update', ['name' => 'finish-mod', 'version' => '1.0.1-beta']);
        $report = PostUpdateReportDTO::fromOutput(Artisan::output());

        $this->assertNotNull($report);
        $this->assertSame([], $report->seeders);
        $this->assertSame(0, DB::table('finish_mod_items')->count());
    }

    public function test_a_failed_health_check_leaves_the_database_untouched(): void
    {
        $this->healthCheck(passes: false);

        $this->finish()
            ->expectsOutputToContain('provider failed')
            ->assertExitCode(1);

        $this->assertDatabaseUntouched();
    }

    public function test_a_failed_migration_rolls_back_the_migrations_before_it(): void
    {
        $this->healthCheck(passes: true);
        File::put(
            $this->modulesPath.'/finish-mod/database/migrations/2026_10_04_000001_broken.php',
            "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        throw new RuntimeException('broken migration');\n    }\n};\n",
        );

        $this->finish()
            ->expectsOutputToContain('broken migration')
            ->assertExitCode(1);

        $this->assertDatabaseUntouched();
    }

    public function test_a_failed_seeder_rolls_back_the_migrations(): void
    {
        $this->healthCheck(passes: true);
        File::put($this->modulesPath.'/finish-mod/database/seeders/BrokenSeeder.php', <<<'PHP'
<?php

namespace Modules\FinishMod\Database\Seeders;

use Illuminate\Database\Seeder;

class BrokenSeeder extends Seeder
{
    public function run(): void
    {
        throw new \RuntimeException('broken seeder');
    }
}
PHP);

        $this->finish()
            ->expectsOutputToContain('broken seeder')
            ->assertExitCode(1);

        $this->assertDatabaseUntouched();
    }

    public function test_it_syncs_the_permissions_of_the_new_version(): void
    {
        $this->healthCheck(passes: true);
        $this->registerModulePermission();

        $this->finish()->assertExitCode(0);

        $this->assertTrue(PermissionModel::query()->where('key', 'finishmod:items.manage')->where('module', 'finish-mod')->exists());
    }

    public function test_the_permissions_exist_before_the_migrations_run(): void
    {
        // A migration that grants a new permission to a role must find its row
        $this->healthCheck(passes: true);
        $this->registerModulePermission();
        File::put($this->modulesPath.'/finish-mod/database/migrations/2026_10_04_000001_needs_permission.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('permissions')->where('key', 'finishmod:items.manage')->exists()) {
            throw new RuntimeException('permission row missing');
        }
    }
};
PHP);

        $this->finish()->assertExitCode(0);
    }

    public function test_a_failed_migration_rolls_back_the_synced_permissions(): void
    {
        $this->healthCheck(passes: true);
        $this->registerModulePermission();
        File::put(
            $this->modulesPath.'/finish-mod/database/migrations/2026_10_04_000001_broken.php',
            "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        throw new RuntimeException('broken migration');\n    }\n};\n",
        );

        $this->finish()->assertExitCode(1);

        $this->assertFalse(PermissionModel::query()->where('key', 'finishmod:items.manage')->exists());
    }

    public function test_grants_made_by_the_migrations_are_recorded_for_who_requested_the_update(): void
    {
        $this->healthCheck(passes: true);
        $this->addGrantingMigration();
        $requester = UserModel::factory()->admin()->create();

        $this->finish(['--audit-source' => 'queue', '--audit-actor' => $requester->id])->assertExitCode(0);

        $entry = $this->grantEntry();
        $this->assertSame($requester->id, $entry->actor_id);
        $this->assertSame('queue', $entry->source);
    }

    public function test_without_the_audit_options_grants_are_recorded_as_a_command_without_actor(): void
    {
        $this->healthCheck(passes: true);
        $this->addGrantingMigration();

        $this->finish()->assertExitCode(0);

        $entry = $this->grantEntry();
        $this->assertNull($entry->actor_id);
        $this->assertSame('command', $entry->source);
    }

    #[DataProvider('actorsWithoutAccount')]
    public function test_an_actor_without_account_is_left_out_of_the_history_and_logged(string $actorId): void
    {
        // The account may be gone by the time the queued update runs; the update goes ahead
        $this->healthCheck(passes: true);
        $this->addGrantingMigration();
        $log = Log::spy();

        $this->finish(['--audit-source' => 'queue', '--audit-actor' => $actorId])->assertExitCode(0);

        $entry = $this->grantEntry();
        $this->assertNull($entry->actor_id);
        $this->assertSame('queue', $entry->source);
        $log->shouldHaveReceived('warning')->once();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actorsWithoutAccount(): array
    {
        return [
            'no such account' => ['019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b'],
            'not an id' => ['not-a-uuid'],
        ];
    }

    public function test_an_unknown_audit_source_fails_without_touching_the_database(): void
    {
        $this->healthCheck(passes: true);

        $this->finish(['--audit-source' => 'cron'])
            ->expectsOutputToContain('cron')
            ->assertExitCode(1);

        $this->assertDatabaseUntouched();
    }

    public function test_it_fails_for_an_unknown_module(): void
    {
        $this->artisanCommand('module:finish-update', ['name' => 'missing-mod', 'version' => '1.0.1-beta'])->assertExitCode(1);
    }

    /**
     * @param  array<string, string>  $options
     */
    private function finish(array $options = []): PendingCommand
    {
        $command = $this->artisan('module:finish-update', ['name' => 'finish-mod', 'version' => '1.0.1-beta', ...$options]);
        assert($command instanceof PendingCommand);

        return $command;
    }

    private function assertDatabaseUntouched(): void
    {
        $this->assertFalse(Schema::hasTable('finish_mod_items'));
        $this->assertFalse(DB::table('migrations')->where('migration', '2026_10_04_000000_create_finish_mod_items_table')->exists());
        $this->assertSame('1.0.0-beta', ModuleModel::query()->where('name', 'finish-mod')->value('version'));
        $this->assertSame([], ModuleSeederHistoryModel::getExecutedSeeders('finish-mod'));
    }

    /**
     * What the module's provider registers when the fresh process boots the new version.
     */
    private function registerModulePermission(): void
    {
        $this->app->make(PermissionRegistryInterface::class)->register(new PermissionDefinitionDTO(
            key: 'finishmod:items.manage',
            label: 'Manage items',
            resource: 'items',
            action: 'manage',
            module: 'finish-mod',
        ));
    }

    /**
     * A migration of the new version that grants a permission to a role, as modules do since #200.
     */
    private function addGrantingMigration(): void
    {
        File::put($this->modulesPath.'/finish-mod/database/migrations/2026_10_04_000001_grant_items_permission.php', <<<'PHP'
<?php

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\RolePermissionGrantsInterface;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(RolePermissionGrantsInterface::class)->grantToRole('member', new PermissionDefinitionDTO(
            key: 'finishmod:items.view',
            label: 'View items',
            resource: 'items',
            action: 'view',
            module: 'finish-mod',
        ));
    }
};
PHP);
    }

    private function grantEntry(): RoleAuditEntryModel
    {
        return RoleAuditEntryModel::query()
            ->where('role_name', 'member')
            ->where('event', 'role_updated')
            ->sole();
    }

    private function healthCheck(bool $passes): void
    {
        $checker = Mockery::mock(ModuleHealthCheckerInterface::class);
        $checker->shouldReceive('check')->andReturn($passes
            ? new HealthCheckResultDTO(true, true, true)
            : new HealthCheckResultDTO(false, true, true, ['provider failed']));
        $this->app->instance(ModuleHealthCheckerInterface::class, $checker);
    }

    private function makeModule(): void
    {
        $root = $this->modulesPath.'/finish-mod';
        File::ensureDirectoryExists($root.'/database/migrations');
        File::ensureDirectoryExists($root.'/database/seeders');
        File::put($root.'/module.json', json_encode(['name' => 'finish-mod', 'version' => '1.0.1-beta'], JSON_THROW_ON_ERROR));

        File::put($root.'/database/migrations/2026_10_04_000000_create_finish_mod_items_table.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finish_mod_items', function (Blueprint $table): void {
            $table->string('name')->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finish_mod_items');
    }
};
PHP);

        // The entry seeder calls a sibling seeder that nothing autoloads (database/ is outside src/)
        File::put($root.'/database/seeders/FinishModDatabaseSeeder.php', <<<'PHP'
<?php

namespace Modules\FinishMod\Database\Seeders;

use Illuminate\Database\Seeder;

class FinishModDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ItemsSeeder::class);
    }
}
PHP);
        File::put($root.'/database/seeders/ItemsSeeder.php', <<<'PHP'
<?php

namespace Modules\FinishMod\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ItemsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('finish_mod_items')->updateOrInsert(['name' => 'default']);
    }
}
PHP);

        ModuleModel::factory()->enabled()->create([
            'name' => 'finish-mod',
            'version' => '1.0.0-beta',
            'latest_available_version' => '1.0.1-beta',
            'path' => $root,
            'namespace' => 'Modules\\FinishMod',
            'provider' => 'FinishModServiceProvider',
            'installed_at' => now(),
        ]);
    }
}
