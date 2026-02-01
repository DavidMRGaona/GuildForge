<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Updates\Services\ModuleHealthChecker;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ModuleHealthCheckerTest extends TestCase
{
    private MockInterface&ModuleManagerServiceInterface $moduleManager;

    private ModuleHealthChecker $service;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleManager = Mockery::mock(ModuleManagerServiceInterface::class);
        $this->service = new ModuleHealthChecker($this->moduleManager);

        $this->tempDir = storage_path('app/test-modules');
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_it_passes_when_all_checks_pass(): void
    {
        $modulePath = $this->createValidModule('healthymod');
        $module = $this->createRealModule('healthymod', $modulePath, 'HealthymodModuleServiceProvider');

        // Load the provider class manually
        require_once "{$modulePath}/src/HealthymodModuleServiceProvider.php";

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        $result = $this->service->check(ModuleName::fromString('healthymod'));

        $this->assertTrue($result->providerLoads);
        $this->assertTrue($result->routesRespond);
        $this->assertTrue($result->filamentRegisters);
        $this->assertTrue($result->passes());
        $this->assertEmpty($result->errors);
    }

    public function test_it_fails_when_module_not_found(): void
    {
        $this->moduleManager->shouldReceive('find')
            ->andReturn(null);

        $result = $this->service->check(ModuleName::fromString('missing'));

        $this->assertFalse($result->providerLoads);
        $this->assertFalse($result->passes());
    }

    public function test_it_fails_when_provider_class_not_found(): void
    {
        $modulePath = $this->createValidModule('noprovider');
        $module = $this->createRealModule('noprovider', $modulePath, 'NonExistentProvider');

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        $result = $this->service->checkProviderLoads(ModuleName::fromString('noprovider'));

        $this->assertFalse($result);
    }

    public function test_it_passes_routes_check_when_no_routes_exist(): void
    {
        $modulePath = $this->createModuleWithoutRoutes('noroutes');
        $module = $this->createRealModule('noroutes', $modulePath, 'Provider');

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        $result = $this->service->checkRoutesRespond(ModuleName::fromString('noroutes'));

        $this->assertTrue($result);
    }

    public function test_it_fails_routes_check_when_php_syntax_is_invalid(): void
    {
        $modulePath = $this->createModuleWithInvalidRoutes('badroutes');
        $module = $this->createRealModule('badroutes', $modulePath, 'Provider');

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        $result = $this->service->checkRoutesRespond(ModuleName::fromString('badroutes'));

        $this->assertFalse($result);
    }

    public function test_it_passes_filament_check_when_no_filament_resources_exist(): void
    {
        $modulePath = $this->createModuleWithoutFilament('nofilament');
        $module = $this->createRealModule('nofilament', $modulePath, 'Provider');

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        $result = $this->service->checkFilamentResources(ModuleName::fromString('nofilament'));

        $this->assertTrue($result);
    }

    public function test_it_fails_filament_check_when_resource_has_syntax_error(): void
    {
        $modulePath = $this->createModuleWithInvalidFilament('badfilament');
        $module = $this->createRealModule('badfilament', $modulePath, 'Provider');

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        $result = $this->service->checkFilamentResources(ModuleName::fromString('badfilament'));

        $this->assertFalse($result);
    }

    public function test_it_collects_errors_from_failed_checks(): void
    {
        $this->moduleManager->shouldReceive('find')
            ->andReturn(null);

        $result = $this->service->check(ModuleName::fromString('allfail'));

        $this->assertFalse($result->passes());
        $this->assertNotEmpty($result->errors);
        $this->assertContains('Service provider failed to load', $result->errors);
    }

    public function test_it_collects_warnings_from_partial_failures(): void
    {
        $modulePath = $this->createModuleWithInvalidRoutes('partialfail');
        $module = $this->createRealModule('partialfail', $modulePath, 'PartialfailModuleServiceProvider');

        // Create a valid provider class file
        $providerPath = "{$modulePath}/src/PartialfailModuleServiceProvider.php";
        File::ensureDirectoryExists(dirname($providerPath));
        File::put($providerPath, '<?php
namespace Modules\Partialfail;

use Illuminate\Support\ServiceProvider;

class PartialfailModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}
}');

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        // Load the provider class manually for the test
        require_once $providerPath;

        $result = $this->service->check(ModuleName::fromString('partialfail'));

        // Provider should load, but routes should fail
        $this->assertTrue($result->providerLoads);
        $this->assertFalse($result->routesRespond);
        $this->assertNotEmpty($result->warnings);
    }

    public function test_full_health_check_dto_structure(): void
    {
        $this->moduleManager->shouldReceive('find')
            ->andReturn(null);

        $result = $this->service->check(ModuleName::fromString('test'));

        // Verify DTO has all expected properties
        $this->assertIsBool($result->providerLoads);
        $this->assertIsBool($result->routesRespond);
        $this->assertIsBool($result->filamentRegisters);
        $this->assertIsArray($result->errors);
        $this->assertIsArray($result->warnings);
    }

    private function createValidModule(string $name): string
    {
        $modulePath = "{$this->tempDir}/{$name}";
        $ucName = ucfirst($name);

        File::ensureDirectoryExists("{$modulePath}/src");
        File::ensureDirectoryExists("{$modulePath}/routes");
        File::ensureDirectoryExists("{$modulePath}/src/Filament/Resources");

        // Valid provider
        File::put("{$modulePath}/src/{$ucName}ModuleServiceProvider.php", "<?php
namespace Modules\\{$ucName};

use Illuminate\\Support\\ServiceProvider;

class {$ucName}ModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}
}");

        // Valid routes
        File::put("{$modulePath}/routes/web.php", "<?php\n// Valid routes file");

        return $modulePath;
    }

    private function createModuleWithoutRoutes(string $name): string
    {
        $modulePath = "{$this->tempDir}/{$name}";
        File::ensureDirectoryExists("{$modulePath}/src");

        return $modulePath;
    }

    private function createModuleWithInvalidRoutes(string $name): string
    {
        $modulePath = "{$this->tempDir}/{$name}";
        File::ensureDirectoryExists("{$modulePath}/routes");

        // Invalid PHP syntax
        File::put("{$modulePath}/routes/web.php", "<?php\nthis is invalid php syntax {{{");

        return $modulePath;
    }

    private function createModuleWithoutFilament(string $name): string
    {
        $modulePath = "{$this->tempDir}/{$name}";
        File::ensureDirectoryExists("{$modulePath}/src");

        return $modulePath;
    }

    private function createModuleWithInvalidFilament(string $name): string
    {
        $modulePath = "{$this->tempDir}/{$name}";
        File::ensureDirectoryExists("{$modulePath}/src/Filament/Resources");

        // Invalid PHP syntax in resource
        File::put("{$modulePath}/src/Filament/Resources/TestResource.php", "<?php\nclass broken {{{");

        return $modulePath;
    }

    private function createRealModule(string $name, string $path, string $providerClass): Module
    {
        return new Module(
            id: new ModuleId(Str::uuid()->toString()),
            name: ModuleName::fromString($name),
            displayName: ucfirst($name) . ' Module',
            description: 'Test module',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
            namespace: 'Modules\\' . ucfirst($name),
            provider: $providerClass,
            path: $path,
        );
    }
}
