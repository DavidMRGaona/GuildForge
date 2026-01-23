<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ScaffoldResultDTO;
use App\Infrastructure\Modules\Services\ModuleScaffoldingService;
use App\Infrastructure\Modules\Services\StubRenderer;
use PHPUnit\Framework\TestCase;

final class ModuleScaffoldingServiceTest extends TestCase
{
    private string $tempModulesPath;

    private StubRenderer $stubRenderer;

    private ModuleScaffoldingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory for test modules
        $this->tempModulesPath = sys_get_temp_dir() . '/guildforge-test-modules-' . uniqid();
        mkdir($this->tempModulesPath, 0755, true);

        // Use real stubs path - stubs are in src/stubs/modules
        $stubsPath = dirname(__DIR__, 5) . '/stubs/modules';
        $this->stubRenderer = new StubRenderer($stubsPath);
        $this->service = new ModuleScaffoldingService($this->stubRenderer, $this->tempModulesPath);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempModulesPath)) {
            $this->deleteDirectory($this->tempModulesPath);
        }

        parent::tearDown();
    }

    public function test_it_creates_module_successfully(): void
    {
        $result = $this->service->createModule('blog', 'A blog module', 'Test Author');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Module 'blog' created successfully", $result->message);
        $this->assertDirectoryExists($this->tempModulesPath . '/blog');
        $this->assertFileExists($this->tempModulesPath . '/blog/module.json');
        $this->assertFileExists($this->tempModulesPath . '/blog/src/BlogServiceProvider.php');
    }

    public function test_it_creates_module_with_correct_structure(): void
    {
        $result = $this->service->createModule('shop');

        $this->assertTrue($result->isSuccess());

        // Verify core files
        $this->assertArrayHasKey('module.json', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['module.json']);

        $this->assertArrayHasKey('src/ShopServiceProvider.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['src/ShopServiceProvider.php']);

        $this->assertArrayHasKey('config/module.php', $result->files);
        $this->assertArrayHasKey('routes/web.php', $result->files);
        $this->assertArrayHasKey('routes/api.php', $result->files);
        $this->assertArrayHasKey('lang/es/messages.php', $result->files);

        // Verify directory structure with .gitkeep files
        $this->assertArrayHasKey('database/migrations/.gitkeep', $result->files);
        $this->assertArrayHasKey('src/Domain/Entities/.gitkeep', $result->files);
        $this->assertArrayHasKey('src/Application/DTOs/.gitkeep', $result->files);
        $this->assertArrayHasKey('src/Infrastructure/Persistence/Eloquent/.gitkeep', $result->files);
    }

    public function test_it_creates_module_with_correct_namespace_in_files(): void
    {
        $result = $this->service->createModule('inventory');

        $this->assertTrue($result->isSuccess());

        // Check that service provider has correct namespace and class name
        $serviceProviderContent = file_get_contents($this->tempModulesPath . '/inventory/src/InventoryServiceProvider.php');
        $this->assertStringContainsString('namespace Modules\Inventory;', $serviceProviderContent);
        $this->assertStringContainsString('class InventoryServiceProvider', $serviceProviderContent);
    }

    public function test_it_rejects_reserved_module_names(): void
    {
        $reservedNames = ['core', 'app', 'admin', 'system', 'modules', 'api', 'web', 'auth', 'user', 'filament'];

        foreach ($reservedNames as $name) {
            $result = $this->service->createModule($name);

            $this->assertTrue($result->isFailure());
            $this->assertStringContainsString("Cannot create module '{$name}': name is reserved", $result->message);
            $this->assertCount(1, $result->errors);
            $this->assertStringContainsString('is a reserved module name', $result->errors[0]);
        }
    }

    public function test_it_rejects_duplicate_module(): void
    {
        // Create first module
        $result1 = $this->service->createModule('forum');
        $this->assertTrue($result1->isSuccess());

        // Try to create duplicate
        $result2 = $this->service->createModule('forum');
        $this->assertTrue($result2->isFailure());
        $this->assertStringContainsString("Module 'forum' already exists", $result2->message);
        $this->assertCount(1, $result2->errors);
    }

    public function test_it_rejects_invalid_module_name_format(): void
    {
        $invalidNames = ['Blog', 'blog_module', 'blog module', '123blog', 'blog!'];

        foreach ($invalidNames as $name) {
            $result = $this->service->createModule($name);

            $this->assertTrue($result->isFailure());
            $this->assertStringContainsString("Invalid module name '{$name}'", $result->message);
        }
    }

    public function test_it_creates_entity_with_migration(): void
    {
        // First create module
        $this->service->createModule('catalog');

        $result = $this->service->createEntity('catalog', 'Product', true);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Entity 'Product' created for module 'catalog'", $result->message);

        // Check entity files
        $this->assertArrayHasKey('src/Domain/Entities/Product.php', $result->files);
        $this->assertArrayHasKey('src/Domain/ValueObjects/ProductId.php', $result->files);
        $this->assertArrayHasKey('src/Domain/Repositories/ProductRepositoryInterface.php', $result->files);
        $this->assertArrayHasKey('src/Infrastructure/Persistence/Eloquent/ProductModel.php', $result->files);
        $this->assertArrayHasKey('src/Infrastructure/Persistence/Eloquent/EloquentProductRepository.php', $result->files);

        // Check migration file exists
        $migrationFiles = array_filter(array_keys($result->files), fn ($key) => str_contains($key, 'database/migrations/'));
        $this->assertCount(1, $migrationFiles);
    }

    public function test_it_creates_entity_without_migration(): void
    {
        $this->service->createModule('store');

        $result = $this->service->createEntity('store', 'Category', false);

        $this->assertTrue($result->isSuccess());

        // Check entity files exist
        $this->assertArrayHasKey('src/Domain/Entities/Category.php', $result->files);
        $this->assertArrayHasKey('src/Domain/ValueObjects/CategoryId.php', $result->files);

        // Check no migration file
        $migrationFiles = array_filter(array_keys($result->files), fn ($key) => str_contains($key, 'database/migrations/'));
        $this->assertCount(0, $migrationFiles);
    }

    public function test_it_validates_module_exists_for_entity(): void
    {
        $result = $this->service->createEntity('nonexistent', 'Product');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'nonexistent' does not exist", $result->message);
    }

    public function test_it_creates_default_controller(): void
    {
        $this->service->createModule('portal');

        $result = $this->service->createController('portal', 'Dashboard');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Controller 'Dashboard' created for module 'portal'", $result->message);
        $this->assertArrayHasKey('src/Http/Controllers/DashboardController.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['src/Http/Controllers/DashboardController.php']);
    }

    public function test_it_creates_resource_controller(): void
    {
        $this->service->createModule('library');

        $result = $this->service->createController('library', 'Book', 'resource');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('src/Http/Controllers/BookController.php', $result->files);

        // Check file content has resource methods
        $content = file_get_contents($this->tempModulesPath . '/library/src/Http/Controllers/BookController.php');
        $this->assertStringContainsString('class BookController', $content);
    }

    public function test_it_creates_api_controller(): void
    {
        $this->service->createModule('gateway');

        $result = $this->service->createController('gateway', 'User', 'api');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('src/Http/Controllers/Api/UserController.php', $result->files);
        $this->assertFileExists($this->tempModulesPath . '/gateway/src/Http/Controllers/Api/UserController.php');
    }

    public function test_it_creates_invokable_controller(): void
    {
        $this->service->createModule('webhook');

        $result = $this->service->createController('webhook', 'ProcessPayment', 'invokable');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('src/Http/Controllers/ProcessPaymentController.php', $result->files);
    }

    public function test_it_skips_existing_controller(): void
    {
        $this->service->createModule('cms');

        // Create first time
        $result1 = $this->service->createController('cms', 'Page');
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result1->files['src/Http/Controllers/PageController.php']);

        // Try to create again
        $result2 = $this->service->createController('cms', 'Page');
        $this->assertEquals(ScaffoldResultDTO::STATUS_SKIPPED, $result2->files['src/Http/Controllers/PageController.php']);
    }

    public function test_it_validates_module_exists_for_controller(): void
    {
        $result = $this->service->createController('missing', 'Test');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'missing' does not exist", $result->message);
    }

    public function test_it_creates_request(): void
    {
        $this->service->createModule('forms');

        $result = $this->service->createRequest('forms', 'CreatePost');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Request 'CreatePost' created for module 'forms'", $result->message);
        $this->assertArrayHasKey('src/Http/Requests/CreatePostRequest.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['src/Http/Requests/CreatePostRequest.php']);
    }

    public function test_it_validates_module_exists_for_request(): void
    {
        $result = $this->service->createRequest('ghost', 'Test');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'ghost' does not exist", $result->message);
    }

    public function test_it_creates_service_with_interface(): void
    {
        $this->service->createModule('payments');

        $result = $this->service->createService('payments', 'PaymentProcessor', true);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Service 'PaymentProcessor' created for module 'payments'", $result->message);
        $this->assertArrayHasKey('src/Infrastructure/Services/PaymentProcessorService.php', $result->files);
        $this->assertArrayHasKey('src/Application/Services/PaymentProcessorServiceInterface.php', $result->files);
    }

    public function test_it_creates_service_without_interface(): void
    {
        $this->service->createModule('utils');

        $result = $this->service->createService('utils', 'Logger', false);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('src/Infrastructure/Services/LoggerService.php', $result->files);
        $this->assertArrayNotHasKey('src/Application/Services/LoggerServiceInterface.php', $result->files);
    }

    public function test_it_validates_module_exists_for_service(): void
    {
        $result = $this->service->createService('void', 'Test');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'void' does not exist", $result->message);
    }

    public function test_it_creates_dto(): void
    {
        $this->service->createModule('orders');

        $result = $this->service->createDto('orders', 'CreateOrder', false);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("DTO 'CreateOrder' created for module 'orders'", $result->message);
        $this->assertArrayHasKey('src/Application/DTOs/CreateOrderDTO.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['src/Application/DTOs/CreateOrderDTO.php']);
    }

    public function test_it_creates_response_dto(): void
    {
        $this->service->createModule('shipment');

        $result = $this->service->createDto('shipment', 'OrderDetail', true);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('src/Application/DTOs/OrderDetailResponseDTO.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['src/Application/DTOs/OrderDetailResponseDTO.php']);
    }

    public function test_it_validates_module_exists_for_dto(): void
    {
        $result = $this->service->createDto('phantom', 'Test');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'phantom' does not exist", $result->message);
    }

    public function test_it_creates_filament_resource(): void
    {
        $this->service->createModule('content');

        $result = $this->service->createFilamentResource('content', 'Post');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Filament resource 'Post' created for module 'content'", $result->message);

        // Check resource files
        $this->assertArrayHasKey('src/Filament/Resources/PostResource.php', $result->files);
        $this->assertArrayHasKey('src/Filament/Resources/PostResource/Pages/ListPosts.php', $result->files);
        $this->assertArrayHasKey('src/Filament/Resources/PostResource/Pages/CreatePost.php', $result->files);
        $this->assertArrayHasKey('src/Filament/Resources/PostResource/Pages/EditPost.php', $result->files);

        // Verify all files were created
        foreach ($result->files as $file => $status) {
            $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $status, "File {$file} should be created");
        }
    }

    public function test_it_validates_module_exists_for_filament_resource(): void
    {
        $result = $this->service->createFilamentResource('shadow', 'Test');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'shadow' does not exist", $result->message);
    }

    public function test_it_creates_migration(): void
    {
        $this->service->createModule('database');

        $result = $this->service->createMigration('database', 'create_users_table', 'database_users');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Migration 'create_users_table' created for module 'database'", $result->message);

        // Check migration file exists
        $migrationFiles = array_keys($result->files);
        $this->assertCount(1, $migrationFiles);
        $this->assertStringContainsString('database/migrations/', $migrationFiles[0]);
        $this->assertStringContainsString('_create_users_table.php', $migrationFiles[0]);
    }

    public function test_it_creates_migration_with_auto_table_name(): void
    {
        $this->service->createModule('blog');

        $result = $this->service->createMigration('blog', 'create_posts_table');

        $this->assertTrue($result->isSuccess());
        $migrationFiles = array_keys($result->files);
        $this->assertCount(1, $migrationFiles);
        $this->assertStringContainsString('_create_posts_table.php', $migrationFiles[0]);
    }

    public function test_it_validates_module_exists_for_migration(): void
    {
        $result = $this->service->createMigration('nowhere', 'test_migration');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'nowhere' does not exist", $result->message);
    }

    public function test_it_creates_unit_test(): void
    {
        $this->service->createModule('testing');

        $result = $this->service->createTest('testing', 'UserService', 'unit');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Test 'UserService' created for module 'testing'", $result->message);
        $this->assertArrayHasKey('tests/Unit/UserServiceTest.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['tests/Unit/UserServiceTest.php']);
    }

    public function test_it_creates_feature_test(): void
    {
        $this->service->createModule('integration');

        $result = $this->service->createTest('integration', 'PostController', 'feature');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('tests/Feature/PostControllerTest.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['tests/Feature/PostControllerTest.php']);
    }

    public function test_it_validates_module_exists_for_test(): void
    {
        $result = $this->service->createTest('illusion', 'Test');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'illusion' does not exist", $result->message);
    }

    public function test_it_creates_vue_page(): void
    {
        $this->service->createModule('frontend');

        $result = $this->service->createVuePage('frontend', 'Dashboard');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Vue page 'Dashboard' created for module 'frontend'", $result->message);
        $this->assertArrayHasKey('resources/js/pages/Dashboard.vue', $result->files);
        $this->assertArrayHasKey('resources/js/types/Dashboard.ts', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['resources/js/pages/Dashboard.vue']);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['resources/js/types/Dashboard.ts']);
    }

    public function test_it_validates_module_exists_for_vue_page(): void
    {
        $result = $this->service->createVuePage('mirage', 'Test');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'mirage' does not exist", $result->message);
    }

    public function test_it_creates_vue_component(): void
    {
        $this->service->createModule('ui');

        $result = $this->service->createVueComponent('ui', 'Button');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Vue component 'Button' created for module 'ui'", $result->message);
        $this->assertArrayHasKey('resources/js/components/Button.vue', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['resources/js/components/Button.vue']);
    }

    public function test_it_validates_module_exists_for_vue_component(): void
    {
        $result = $this->service->createVueComponent('vapor', 'Test');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'vapor' does not exist", $result->message);
    }

    public function test_it_generates_correct_file_count(): void
    {
        $result = $this->service->createModule('metrics');

        $this->assertTrue($result->isSuccess());

        // Count created files
        $createdCount = $result->countFilesByStatus(ScaffoldResultDTO::STATUS_CREATED);
        $this->assertGreaterThan(0, $createdCount);
        $this->assertStringContainsString("with {$createdCount} files", $result->message);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), ['.', '..']);

        foreach ($items as $item) {
            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
