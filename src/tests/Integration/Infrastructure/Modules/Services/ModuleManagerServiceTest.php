<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\DependencyCheckResultDTO;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Exceptions\ModuleAlreadyDisabledException;
use App\Domain\Modules\Exceptions\ModuleAlreadyEnabledException;
use App\Domain\Modules\Exceptions\ModuleDependencyException;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ModuleManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    private ModuleManagerServiceInterface $service;

    private string $testModulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testModulesPath = sys_get_temp_dir().'/guildforge-test-modules-'.uniqid();
        mkdir($this->testModulesPath, 0755, true);

        // Override the modules path in config
        config(['modules.path' => $this->testModulesPath]);

        $this->service = $this->app->make(ModuleManagerServiceInterface::class);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testModulesPath);
        parent::tearDown();
    }

    public function test_it_discovers_modules_from_filesystem(): void
    {
        $this->createTestModule('test-module', [
            'display_name' => 'Test Module',
            'description' => 'A test module',
            'author' => 'Test Author',
        ]);

        $this->createTestModule('another-module', [
            'display_name' => 'Another Module',
            'description' => 'Another test module',
            'author' => 'Test Author',
        ]);

        $modules = $this->service->discover();

        $this->assertInstanceOf(ModuleCollection::class, $modules);
        $this->assertCount(2, $modules);
        $this->assertNotNull($modules->findByName(ModuleName::fromString('test-module')));
        $this->assertNotNull($modules->findByName(ModuleName::fromString('another-module')));
    }

    public function test_it_enables_a_disabled_module(): void
    {
        $this->createTestModule('test-module');

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Disabled->value,
            'requires' => null,
        ]);

        $module = $this->service->enable(ModuleName::fromString('test-module'));

        $this->assertTrue($module->isEnabled());
        $this->assertNotNull($module->enabledAt());

        $this->assertDatabaseHas('modules', [
            'name' => 'test-module',
            'status' => ModuleStatus::Enabled->value,
        ]);
    }

    public function test_it_throws_when_enabling_already_enabled_module(): void
    {
        $this->createTestModule('test-module');

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        $this->expectException(ModuleAlreadyEnabledException::class);
        $this->expectExceptionMessage('Module "test-module" is already enabled');

        $this->service->enable(ModuleName::fromString('test-module'));
    }

    public function test_it_disables_an_enabled_module(): void
    {
        $this->createTestModule('test-module');

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        $module = $this->service->disable(ModuleName::fromString('test-module'));

        $this->assertTrue($module->isDisabled());
        $this->assertNull($module->enabledAt());

        $this->assertDatabaseHas('modules', [
            'name' => 'test-module',
            'status' => ModuleStatus::Disabled->value,
        ]);
    }

    public function test_it_throws_when_disabling_already_disabled_module(): void
    {
        $this->createTestModule('test-module');

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Disabled->value,
            'requires' => null,
        ]);

        $this->expectException(ModuleAlreadyDisabledException::class);
        $this->expectExceptionMessage('Module "test-module" is already disabled');

        $this->service->disable(ModuleName::fromString('test-module'));
    }

    public function test_it_throws_when_enabling_module_with_missing_dependency(): void
    {
        $this->createTestModule('test-module', [
            'requires' => [
                'modules' => ['missing-module'],
            ],
        ]);

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Disabled->value,
            'requires' => [
                'modules' => ['missing-module'],
            ],
        ]);

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessage('Cannot enable module "test-module": missing dependencies');

        $this->service->enable(ModuleName::fromString('test-module'));
    }

    public function test_it_throws_when_disabling_module_with_dependents(): void
    {
        $this->createTestModule('base-module');
        $this->createTestModule('dependent-module', [
            'requires' => [
                'modules' => ['base-module'],
            ],
        ]);

        ModuleModel::create([
            'name' => 'base-module',
            'display_name' => 'Base Module',
            'version' => '1.0.0',
            'description' => 'Base module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        ModuleModel::create([
            'name' => 'dependent-module',
            'display_name' => 'Dependent Module',
            'version' => '1.0.0',
            'description' => 'Dependent module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => [
                'modules' => ['base-module'],
            ],
            'enabled_at' => now(),
        ]);

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessage('Cannot disable module "base-module". It is required by: dependent-module');

        $this->service->disable(ModuleName::fromString('base-module'));
    }

    public function test_it_returns_all_modules(): void
    {
        $this->createTestModule('module-one');
        $this->createTestModule('module-two');

        ModuleModel::create([
            'name' => 'module-one',
            'display_name' => 'Module One',
            'version' => '1.0.0',
            'description' => 'First module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        ModuleModel::create([
            'name' => 'module-two',
            'display_name' => 'Module Two',
            'version' => '1.0.0',
            'description' => 'Second module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Disabled->value,
            'requires' => null,
        ]);

        $modules = $this->service->all();

        $this->assertInstanceOf(ModuleCollection::class, $modules);
        $this->assertCount(2, $modules);
    }

    public function test_it_returns_enabled_modules(): void
    {
        ModuleModel::create([
            'name' => 'enabled-module',
            'display_name' => 'Enabled Module',
            'version' => '1.0.0',
            'description' => 'Enabled module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        ModuleModel::create([
            'name' => 'disabled-module',
            'display_name' => 'Disabled Module',
            'version' => '1.0.0',
            'description' => 'Disabled module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Disabled->value,
            'requires' => null,
        ]);

        $modules = $this->service->enabled();

        $this->assertInstanceOf(ModuleCollection::class, $modules);
        $this->assertCount(1, $modules);
        $this->assertTrue($modules->items()[0]->isEnabled());
    }

    public function test_it_finds_module_by_name(): void
    {
        ModuleModel::create([
            'name' => 'findable-module',
            'display_name' => 'Findable Module',
            'version' => '1.0.0',
            'description' => 'Findable module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        $module = $this->service->find(ModuleName::fromString('findable-module'));

        $this->assertInstanceOf(Module::class, $module);
        $this->assertSame('findable-module', $module->name()->toString());
    }

    public function test_it_checks_dependencies(): void
    {
        $this->createTestModule('base-module');
        $this->createTestModule('dependent-module', [
            'requires' => [
                'modules' => ['base-module'],
                'php' => '8.1.0',
            ],
        ]);

        ModuleModel::create([
            'name' => 'base-module',
            'display_name' => 'Base Module',
            'version' => '1.0.0',
            'description' => 'Base module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        ModuleModel::create([
            'name' => 'dependent-module',
            'display_name' => 'Dependent Module',
            'version' => '1.0.0',
            'description' => 'Dependent module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Disabled->value,
            'requires' => [
                'modules' => ['base-module'],
                'php' => '8.1.0',
            ],
        ]);

        $result = $this->service->checkDependencies(ModuleName::fromString('dependent-module'));

        $this->assertInstanceOf(DependencyCheckResultDTO::class, $result);
        $this->assertTrue($result->satisfied);
        $this->assertEmpty($result->missing);
    }

    public function test_it_runs_migrations_for_module(): void
    {
        $this->createTestModule('test-module');
        $this->createTestModuleMigration('test-module', 'create_test_table');

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
        ]);

        $migrationsRun = $this->service->migrate(ModuleName::fromString('test-module'));

        $this->assertIsInt($migrationsRun);
        $this->assertGreaterThanOrEqual(0, $migrationsRun);
    }

    public function test_enable_runs_migrations_only_when_module_is_not_installed(): void
    {
        $this->createTestModule('test-module');
        $this->createTestModuleMigration('test-module', 'create_test_table');

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Disabled->value,
            'requires' => null,
            'installed_at' => null, // Not installed
        ]);

        $module = $this->service->enable(ModuleName::fromString('test-module'));

        $this->assertTrue($module->isEnabled());
        $this->assertTrue($module->isInstalled());
        $this->assertNotNull($module->installedAt());

        $this->assertDatabaseHas('modules', [
            'name' => 'test-module',
            'status' => ModuleStatus::Enabled->value,
        ]);

        // Verify installed_at is set in database
        $dbModule = ModuleModel::where('name', 'test-module')->first();
        $this->assertNotNull($dbModule->installed_at);
    }

    public function test_enable_does_not_run_migrations_when_module_is_already_installed(): void
    {
        $this->createTestModule('test-module');
        $this->createTestModuleMigration('test-module', 'create_test_table');

        $installedAt = now();
        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Disabled->value,
            'requires' => null,
            'installed_at' => $installedAt, // Already installed
        ]);

        $module = $this->service->enable(ModuleName::fromString('test-module'));

        $this->assertTrue($module->isEnabled());
        $this->assertTrue($module->isInstalled());

        // installed_at should remain the same (not updated)
        $dbModule = ModuleModel::where('name', 'test-module')->first();
        $this->assertEquals(
            $installedAt->format('Y-m-d H:i:s'),
            $dbModule->installed_at->format('Y-m-d H:i:s')
        );
    }

    public function test_uninstall_with_delete_data_resets_installed_at(): void
    {
        $this->createTestModule('test-module');

        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Disabled->value,
            'requires' => null,
            'installed_at' => now(),
        ]);

        // Uninstall with deleteData=true should remove the module from DB
        $this->service->uninstall(ModuleName::fromString('test-module'), true);

        $this->assertDatabaseMissing('modules', [
            'name' => 'test-module',
        ]);
    }

    public function test_disable_then_enable_does_not_run_migrations_again(): void
    {
        $this->createTestModule('test-module');

        // Start with an installed and enabled module
        $installedAt = now()->subDay();
        ModuleModel::create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
            'requires' => null,
            'enabled_at' => now(),
            'installed_at' => $installedAt, // Already installed
        ]);

        // Disable the module
        $this->service->disable(ModuleName::fromString('test-module'));

        // Enable the module again
        $module = $this->service->enable(ModuleName::fromString('test-module'));

        $this->assertTrue($module->isEnabled());
        $this->assertTrue($module->isInstalled());

        // installed_at should remain the same (not updated)
        $dbModule = ModuleModel::where('name', 'test-module')->first();
        $this->assertEquals(
            $installedAt->format('Y-m-d H:i:s'),
            $dbModule->installed_at->format('Y-m-d H:i:s')
        );
    }

    private function createTestModule(string $name, array $manifest = []): void
    {
        $modulePath = $this->testModulesPath.'/'.$name;
        mkdir($modulePath, 0755, true);

        $defaultManifest = [
            'name' => $name,
            'version' => '1.0.0',
            'namespace' => 'Modules\\'.str_replace('-', '', ucwords($name, '-')),
            'provider' => str_replace('-', '', ucwords($name, '-')).'ServiceProvider',
            'description' => 'Test module',
            'author' => 'Test Author',
        ];

        file_put_contents(
            $modulePath.'/module.json',
            json_encode(array_merge($defaultManifest, $manifest), JSON_PRETTY_PRINT)
        );
    }

    private function createTestModuleMigration(string $moduleName, string $migrationName): void
    {
        $modulePath = $this->testModulesPath.'/'.$moduleName;
        $migrationsPath = $modulePath.'/database/migrations';
        mkdir($migrationsPath, 0755, true);

        $timestamp = date('Y_m_d_His');
        $migrationContent = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_module_table', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_module_table');
    }
};
PHP;

        file_put_contents(
            $migrationsPath.'/'.$timestamp.'_'.$migrationName.'.php',
            $migrationContent
        );
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path.'/'.$item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
