<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModuleDependencyResolver;
use App\Infrastructure\Modules\Services\ModuleDiscoveryService;
use App\Infrastructure\Modules\Services\ModuleManagerService;
use App\Infrastructure\Modules\Services\ModuleMigrationRunner;
use App\Infrastructure\Modules\Services\ModuleSeederRunner;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModuleManagerServiceDiscoverTest extends TestCase
{
    private string $tempModulesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempModulesPath = sys_get_temp_dir().'/test-modules-'.uniqid();
        mkdir($this->tempModulesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempModulesPath)) {
            $this->removeDirectory($this->tempModulesPath);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_runs_migrations_and_seeders_when_version_changes_for_enabled_module(): void
    {
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $events = Mockery::mock(Dispatcher::class);

        $this->createModuleManifest('test-module', '1.1.0');
        $this->createModuleMigration('test-module');

        $discoveryService = new ModuleDiscoveryService($this->tempModulesPath);
        $dependencyResolver = new ModuleDependencyResolver;
        $migrationRunner = new ModuleMigrationRunner($this->tempModulesPath);
        $seederRunner = new ModuleSeederRunner($this->tempModulesPath);

        $service = new ModuleManagerService(
            $repository,
            $discoveryService,
            $dependencyResolver,
            $migrationRunner,
            $seederRunner,
            $events
        );

        $module = $this->createModule('test-module', '1.0.0', ModuleStatus::Enabled, new \DateTimeImmutable);
        $module->markInstalled();

        $repository->shouldReceive('exists')
            ->once()
            ->with(Mockery::on(fn (ModuleName $name) => $name->value === 'test-module'))
            ->andReturn(true);

        $repository->shouldReceive('findByName')
            ->once()
            ->with(Mockery::on(fn (ModuleName $name) => $name->value === 'test-module'))
            ->andReturn($module);

        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::type(Module::class));

        // Migration runner will call Artisan::call('migrate', ...)
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', Mockery::any())
            ->andReturn(0);

        Log::shouldReceive('info')
            ->atLeast()
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'Module test-module version synced')
                    && $context['from'] === '1.0.0'
                    && $context['to'] === '1.1.0';
            });

        // Allow other Log::info calls (seeder runner logs)
        Log::shouldReceive('info')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('warning')->withAnyArgs()->andReturnNull();

        $service->discover();

        // Verify the module version was updated
        $this->assertSame('1.1.0', $module->version()->value());
    }

    #[Test]
    public function it_skips_migrations_when_version_changes_for_disabled_module(): void
    {
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $events = Mockery::mock(Dispatcher::class);

        $this->createModuleManifest('test-module', '1.1.0');
        $this->createModuleMigration('test-module');

        $discoveryService = new ModuleDiscoveryService($this->tempModulesPath);
        $dependencyResolver = new ModuleDependencyResolver;
        $migrationRunner = new ModuleMigrationRunner($this->tempModulesPath);
        $seederRunner = new ModuleSeederRunner($this->tempModulesPath);

        $service = new ModuleManagerService(
            $repository,
            $discoveryService,
            $dependencyResolver,
            $migrationRunner,
            $seederRunner,
            $events
        );

        $module = $this->createModule('test-module', '1.0.0', ModuleStatus::Disabled, null);

        $repository->shouldReceive('exists')
            ->once()
            ->with(Mockery::on(fn (ModuleName $name) => $name->value === 'test-module'))
            ->andReturn(true);

        $repository->shouldReceive('findByName')
            ->once()
            ->with(Mockery::on(fn (ModuleName $name) => $name->value === 'test-module'))
            ->andReturn($module);

        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::type(Module::class));

        // Artisan migrate should NOT be called for disabled modules
        Artisan::shouldReceive('call')
            ->with('migrate', Mockery::any())
            ->never();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'Module test-module version synced')
                    && $context['from'] === '1.0.0'
                    && $context['to'] === '1.1.0';
            });

        $service->discover();

        // Version should still be updated in the entity
        $this->assertSame('1.1.0', $module->version()->value());
    }

    #[Test]
    public function it_logs_warning_when_migration_fails_during_version_sync(): void
    {
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $events = Mockery::mock(Dispatcher::class);

        $this->createModuleManifest('test-module', '1.1.0');

        $discoveryService = new ModuleDiscoveryService($this->tempModulesPath);
        $dependencyResolver = new ModuleDependencyResolver;
        // Use a separate non-existent base path for the runners so that both
        // the entity path AND the fallback path are missing, triggering
        // ModuleNotFoundException from the runner
        $nonExistentRunnerPath = '/nonexistent/runner/path';
        $migrationRunner = new ModuleMigrationRunner($nonExistentRunnerPath);
        $seederRunner = new ModuleSeederRunner($nonExistentRunnerPath);

        $service = new ModuleManagerService(
            $repository,
            $discoveryService,
            $dependencyResolver,
            $migrationRunner,
            $seederRunner,
            $events
        );

        // Module entity with a path that does NOT exist on disk
        $module = new Module(
            id: ModuleId::generate(),
            name: new ModuleName('test-module'),
            displayName: 'Test Module',
            description: 'A test module',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
            enabledAt: new \DateTimeImmutable,
            createdAt: new \DateTimeImmutable,
            updatedAt: new \DateTimeImmutable,
            namespace: 'Modules\\TestModule',
            provider: 'TestModuleServiceProvider',
            path: '/nonexistent/entity/path/test-module',
            dependencies: [],
        );
        $module->markInstalled();

        $repository->shouldReceive('exists')
            ->once()
            ->with(Mockery::on(fn (ModuleName $name) => $name->value === 'test-module'))
            ->andReturn(true);

        $repository->shouldReceive('findByName')
            ->once()
            ->with(Mockery::on(fn (ModuleName $name) => $name->value === 'test-module'))
            ->andReturn($module);

        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::type(Module::class));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'Failed to run migrations/seeders')
                    && str_contains($message, 'test-module')
                    && $context['previous_version'] === '1.0.0'
                    && $context['new_version'] === '1.1.0'
                    && isset($context['error']);
            });

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'Module test-module version synced')
                    && $context['from'] === '1.0.0'
                    && $context['to'] === '1.1.0';
            });

        // discover() should NOT throw despite migration failure
        $service->discover();

        $this->assertSame('1.1.0', $module->version()->value());
    }

    private function createModule(
        string $name,
        string $version,
        ModuleStatus $status,
        ?\DateTimeImmutable $enabledAt,
    ): Module {
        return new Module(
            id: ModuleId::generate(),
            name: new ModuleName($name),
            displayName: 'Test Module',
            description: 'A test module',
            version: ModuleVersion::fromString($version),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: $status,
            enabledAt: $enabledAt,
            createdAt: new \DateTimeImmutable,
            updatedAt: new \DateTimeImmutable,
            namespace: 'Modules\\TestModule',
            provider: 'TestModuleServiceProvider',
            path: $this->tempModulesPath.'/'.$name,
            dependencies: [],
        );
    }

    private function createModuleManifest(string $name, string $version): void
    {
        $modulePath = $this->tempModulesPath.'/'.$name;
        if (! is_dir($modulePath)) {
            mkdir($modulePath, 0755, true);
        }

        $manifest = [
            'name' => $name,
            'version' => $version,
            'namespace' => 'Modules\\TestModule',
            'provider' => 'TestModuleServiceProvider',
            'description' => 'A test module',
            'author' => 'Test Author',
        ];

        file_put_contents(
            $modulePath.'/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT)
        );
    }

    private function createModuleMigration(string $moduleName): void
    {
        $migrationsPath = $this->tempModulesPath.'/'.$moduleName.'/database/migrations';
        mkdir($migrationsPath, 0755, true);

        $content = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_discover_table', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_discover_table');
    }
};
PHP;

        file_put_contents(
            $migrationsPath.'/'.date('Y_m_d_His').'_create_test_discover_table.php',
            $content
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
