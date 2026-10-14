<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Application\Modules\DTOs\ScaffoldResultDTO;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModuleScaffoldingService;
use App\Infrastructure\Modules\Services\StubRenderer;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->tempModulesPath = sys_get_temp_dir().'/guildforge-test-modules-'.bin2hex(random_bytes(8));
        mkdir($this->tempModulesPath, 0755, true);

        // Use real stubs path - stubs are in src/stubs/modules
        $stubsPath = dirname(__DIR__, 5).'/stubs/modules';
        $this->stubRenderer = new StubRenderer($stubsPath);
        $coreVersion = $this->createStub(CoreVersionServiceInterface::class);
        $coreVersion->method('getCurrentVersion')->willReturn(ModuleVersion::fromString('2.6.3'));
        $this->service = new ModuleScaffoldingService($this->stubRenderer, $this->tempModulesPath, $coreVersion);
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
        $this->assertDirectoryExists($this->tempModulesPath.'/blog');
        $this->assertFileExists($this->tempModulesPath.'/blog/module.json');
        $this->assertFileExists($this->tempModulesPath.'/blog/src/BlogServiceProvider.php');
    }

    public function test_the_generated_manifest_requires_the_running_core_minor(): void
    {
        $this->service->createModule('blog', 'A blog module', 'Test Author');

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($this->tempModulesPath.'/blog/module.json'), true);

        $this->assertSame('^2.6', $manifest['requires']['core'] ?? null);
        $this->assertSame('>=13.0', $manifest['requires']['laravel'] ?? null);
        $this->assertSame('>=8.5', $manifest['requires']['php'] ?? null);
        $this->assertSame('^5.0', $manifest['requires']['filament'] ?? null);
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
        $this->assertArrayHasKey('src/Infrastructure/Persistence/Eloquent/Models/.gitkeep', $result->files);
        $this->assertArrayHasKey('src/Infrastructure/Persistence/Eloquent/Repositories/.gitkeep', $result->files);
    }

    public function test_it_creates_module_with_correct_namespace_in_files(): void
    {
        $result = $this->service->createModule('inventory');

        $this->assertTrue($result->isSuccess());

        // Check that service provider has correct namespace and class name
        $serviceProviderContent = file_get_contents($this->tempModulesPath.'/inventory/src/InventoryServiceProvider.php');
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
        $this->assertArrayHasKey('src/Infrastructure/Persistence/Eloquent/Models/ProductModel.php', $result->files);
        $this->assertArrayHasKey('src/Infrastructure/Persistence/Eloquent/Repositories/EloquentProductRepository.php', $result->files);

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
        $content = file_get_contents($this->tempModulesPath.'/library/src/Http/Controllers/BookController.php');
        $this->assertStringContainsString('class BookController', $content);
    }

    public function test_it_creates_api_controller(): void
    {
        $this->service->createModule('gateway');

        $result = $this->service->createController('gateway', 'User', 'api');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('src/Http/Controllers/Api/UserController.php', $result->files);
        $this->assertFileExists($this->tempModulesPath.'/gateway/src/Http/Controllers/Api/UserController.php');
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
        $this->assertArrayHasKey('tests/Unit/Domain/Entities/UserServiceTest.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['tests/Unit/Domain/Entities/UserServiceTest.php']);
    }

    public function test_it_creates_feature_test(): void
    {
        $this->service->createModule('integration');

        $result = $this->service->createTest('integration', 'PostController', 'feature');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('tests/Feature/Http/Controllers/PostControllerTest.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['tests/Feature/Http/Controllers/PostControllerTest.php']);
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
        $this->assertArrayHasKey('resources/js/pages/Frontend/Dashboard.vue', $result->files);
        $this->assertArrayHasKey('resources/js/types/Dashboard.ts', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['resources/js/pages/Frontend/Dashboard.vue']);
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

    public function test_it_creates_domain_event(): void
    {
        $this->service->createModule('alerts');

        $result = $this->service->createDomainEvent('alerts', 'AlertTriggered');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Domain event 'AlertTriggered' created for module 'alerts'", $result->message);
        $this->assertArrayHasKey('src/Domain/Events/AlertTriggered.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['src/Domain/Events/AlertTriggered.php']);

        // Verify content uses Dispatchable pattern
        $content = file_get_contents($this->tempModulesPath.'/alerts/src/Domain/Events/AlertTriggered.php');
        $this->assertStringContainsString('use Dispatchable;', $content);
        $this->assertStringContainsString('use SerializesModels;', $content);
        $this->assertStringNotContainsString('final readonly class', $content);
    }

    public function test_it_creates_domain_event_validates_module_exists(): void
    {
        $result = $this->service->createDomainEvent('nonexistent', 'SomeEvent');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'nonexistent' does not exist", $result->message);
    }

    public function test_it_creates_listener_in_infrastructure_namespace(): void
    {
        $this->service->createModule('tasks');

        $result = $this->service->createListener('tasks', 'HandleTaskCreated', 'TaskCreated');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Listener 'HandleTaskCreated' created for module 'tasks'", $result->message);
        $this->assertArrayHasKey('src/Infrastructure/Listeners/HandleTaskCreated.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['src/Infrastructure/Listeners/HandleTaskCreated.php']);

        // Verify content uses synchronous pattern
        $content = file_get_contents($this->tempModulesPath.'/tasks/src/Infrastructure/Listeners/HandleTaskCreated.php');
        $this->assertStringContainsString('final readonly class HandleTaskCreated', $content);
        $this->assertStringContainsString('namespace Modules\Tasks\Infrastructure\Listeners;', $content);
        // The uncommented class declaration should not implement ShouldQueue
        $this->assertMatchesRegularExpression('/^final readonly class/m', $content);
    }

    public function test_it_creates_queued_listener(): void
    {
        $this->service->createModule('mailer');

        $result = $this->service->createListener('mailer', 'SendWelcomeEmail', 'UserRegistered', true);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('src/Infrastructure/Listeners/SendWelcomeEmail.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['src/Infrastructure/Listeners/SendWelcomeEmail.php']);

        // Verify content uses queued pattern
        $content = file_get_contents($this->tempModulesPath.'/mailer/src/Infrastructure/Listeners/SendWelcomeEmail.php');
        $this->assertStringContainsString('implements ShouldQueue', $content);
        $this->assertStringContainsString('public function failed(', $content);
        // No worker consumes a "listeners" queue: the listener runs on the default one
        $this->assertDoesNotMatchRegularExpression('/^\s*public string \$queue/m', $content);
        $this->assertStringContainsString('default queue', $content);
    }

    public function test_it_creates_listener_validates_module_exists(): void
    {
        $result = $this->service->createListener('nonexistent', 'SomeListener', 'SomeEvent');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'nonexistent' does not exist", $result->message);
    }

    public function test_it_creates_notification(): void
    {
        $this->service->createModule('comms');

        $result = $this->service->createNotification('comms', 'WelcomeNotification');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString("Notification 'WelcomeNotification' created for module 'comms'", $result->message);
        $this->assertArrayHasKey('src/Notifications/WelcomeNotification.php', $result->files);
        $this->assertEquals(ScaffoldResultDTO::STATUS_CREATED, $result->files['src/Notifications/WelcomeNotification.php']);

        // Verify content
        $content = file_get_contents($this->tempModulesPath.'/comms/src/Notifications/WelcomeNotification.php');
        $this->assertStringContainsString('extends Notification', $content);
        $this->assertStringContainsString('use Queueable;', $content);
        $this->assertStringContainsString('public function toMail(', $content);
    }

    public function test_it_creates_notification_validates_module_exists(): void
    {
        $result = $this->service->createNotification('nonexistent', 'SomeNotification');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString("Module 'nonexistent' does not exist", $result->message);
    }

    public function test_a_new_module_has_the_files_its_tests_and_release_need(): void
    {
        $result = $this->service->createModule('blog-posts');

        foreach (['phpunit.xml', '.gitignore', 'resources/css/module.css', '.github/workflows/release.yml'] as $file) {
            $this->assertSame(ScaffoldResultDTO::STATUS_CREATED, $result->files[$file] ?? null, $file);
            $this->assertFileExists($this->tempModulesPath.'/blog-posts/'.$file);
        }

        $phpunit = simplexml_load_file($this->tempModulesPath.'/blog-posts/phpunit.xml');
        $this->assertNotFalse($phpunit);
        $this->assertSame('../../vendor/autoload.php', (string) $phpunit['bootstrap']);
        $suites = [];
        foreach ($phpunit->testsuites->testsuite as $suite) {
            $suites[(string) $suite['name']] = (string) $suite->directory;
        }
        $this->assertSame(['Unit' => './tests/Unit', 'Integration' => './tests/Integration', 'Feature' => './tests/Feature'], $suites);
        $this->assertStringContainsString('<env name="DB_CONNECTION" value="sqlite"/>', (string) file_get_contents($this->tempModulesPath.'/blog-posts/phpunit.xml'));

        $gitignore = (string) file_get_contents($this->tempModulesPath.'/blog-posts/.gitignore');
        foreach (['/vendor', '/node_modules', '/public/build', '/.phpunit.cache'] as $entry) {
            $this->assertStringContainsString($entry, $gitignore);
        }

        $workflow = (string) file_get_contents($this->tempModulesPath.'/blog-posts/.github/workflows/release.yml');
        $this->assertStringContainsString('reusable-module-release.yml@main', $workflow);
        $this->assertStringContainsString("module_name: 'blog-posts'", $workflow);
    }

    public function test_the_vite_config_builds_like_the_release_workflow_expects(): void
    {
        $this->service->createModule('blog-posts');

        $config = (string) file_get_contents($this->tempModulesPath.'/blog-posts/vite.config.ts');

        $this->assertStringContainsString("const MODULE_NAME = 'blog-posts';", $config);
        $this->assertStringContainsString("process.env.MODULE_STANDALONE_OUTPUT === 'true'", $config);
        $this->assertStringContainsString("resolve(__dirname, '../public')", $config);
        $this->assertStringContainsString('base: (isStandalone || forceStandaloneOutput)', $config);
        $this->assertStringContainsString("discoverEntries(pagesDir, 'resources/js/pages')", $config);
        $this->assertStringContainsString("discoverEntries(componentsDir, 'resources/js/components')", $config);
        $this->assertStringContainsString("['vue', 'vue-i18n', 'pinia', '@inertiajs/vue3', '@unhead/vue']", $config);
        $this->assertStringContainsString("isStandalone && id.startsWith('@/')", $config);
        $this->assertStringContainsString("'module-styles': resolve(__dirname, 'resources/css/module.css')", $config);
        $this->assertStringContainsString('plugins: [vue(), tailwindcss()]', $config);
    }

    public function test_package_json_pins_the_module_toolchain(): void
    {
        $this->service->createModule('blog-posts');

        /** @var array<string, mixed> $package */
        $package = json_decode((string) file_get_contents($this->tempModulesPath.'/blog-posts/package.json'), true);

        $this->assertSame('@guildforge/module-blog-posts', $package['name']);
        $this->assertSame('vite build', $package['scripts']['build']);
        $this->assertSame('^7.3.1', $package['devDependencies']['vite']);
        $this->assertSame('^6.0.3', $package['devDependencies']['@vitejs/plugin-vue']);
        $this->assertSame('^4.1.18', $package['devDependencies']['@tailwindcss/vite']);
    }

    public function test_the_manifest_has_a_display_name_and_no_repository_by_default(): void
    {
        $this->service->createModule('blog-posts');

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($this->tempModulesPath.'/blog-posts/module.json'), true);

        $this->assertSame('Blog posts', $manifest['displayName']);
        $this->assertArrayNotHasKey('repository', $manifest);
        $this->assertSame('Blog posts', ModuleManifestDTO::fromArray($manifest)->displayName);
    }

    public function test_the_manifest_takes_the_given_display_name_repository_and_escaped_texts(): void
    {
        $result = $this->service->createModule(
            'blog-posts',
            'Entradas "destacadas" del blog',
            'Gremio \\ Norte',
            'Entradas del blog',
            'acme/guildforge-blog-posts',
        );

        $this->assertTrue($result->isSuccess());

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($this->tempModulesPath.'/blog-posts/module.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Entradas del blog', $manifest['displayName']);
        $this->assertSame('acme/guildforge-blog-posts', $manifest['repository']);
        $this->assertSame('Entradas "destacadas" del blog', $manifest['description']);
        $this->assertSame('Gremio \\ Norte', $manifest['author']);

        $messages = include $this->tempModulesPath.'/blog-posts/lang/es/messages.php';
        $this->assertSame('Entradas del blog', $messages['title']);
    }

    public function test_an_invalid_repository_is_refused_before_anything_is_written(): void
    {
        $result = $this->service->createModule('blog-posts', repository: 'not a repository');

        $this->assertTrue($result->isFailure());
        $this->assertDirectoryDoesNotExist($this->tempModulesPath.'/blog-posts');
    }

    public function test_the_service_provider_leaves_config_to_the_parent_and_shows_every_hook(): void
    {
        $this->service->createModule('blog-posts');

        $provider = (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/BlogPostsServiceProvider.php');

        $this->assertStringNotContainsString('mergeConfigFrom', $provider);
        $this->assertStringContainsString("new PagePrefixDTO(prefix: 'BlogPosts', module: \$this->moduleName())", $provider);
        foreach (['registerPolicies', 'registerPermissions', 'registerFilamentWidgets', 'registerFilamentPages', 'registerCalendarSources', 'registerPagePrefixes'] as $hook) {
            $this->assertStringContainsString("public function {$hook}(): array", $provider);
        }
        $this->assertStringContainsString('RepositoryInterface::class', $provider);
    }

    public function test_the_module_title_is_a_spanish_style_label(): void
    {
        $this->service->createModule('blog-posts');

        $messages = include $this->tempModulesPath.'/blog-posts/lang/es/messages.php';

        $this->assertSame('Blog posts', $messages['title']);
    }

    public function test_hyphenated_modules_use_the_kebab_case_translation_namespace(): void
    {
        $this->generateEveryTranslatedClass('blog-posts');

        foreach ($this->phpFiles($this->tempModulesPath.'/blog-posts') as $path => $content) {
            $this->assertStringNotContainsString('blog_posts::', $content, $path);
        }

        $resource = (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/Filament/Resources/PostResource.php');
        $this->assertStringContainsString("__('blog-posts::filament.post.label')", $resource);
        $this->assertStringContainsString("__('blog-posts::filament.navigation_group')", $resource);
    }

    public function test_generators_write_the_translation_keys_their_stubs_use(): void
    {
        $this->generateEveryTranslatedClass('blog-posts');
        $langPath = $this->tempModulesPath.'/blog-posts/lang/es';
        $checked = 0;

        foreach ($this->phpFiles($this->tempModulesPath.'/blog-posts/src') as $path => $content) {
            // Commented examples (provider docblocks) are not code
            $code = (string) preg_replace('#^\s*(\*|//).*$#m', '', $content);
            preg_match_all("#__\('blog-posts::([a-z_]+)\.([a-z0-9_.]+)'\)#", $code, $matches, PREG_SET_ORDER);

            foreach ($matches as [, $file, $key]) {
                $this->assertFileExists("{$langPath}/{$file}.php", $path);
                $translations = include "{$langPath}/{$file}.php";
                $this->assertTrue(Arr::has($translations, $key), "{$path}: blog-posts::{$file}.{$key} is not in lang/es/{$file}.php");
                $this->assertIsString(Arr::get($translations, $key), "{$path}: blog-posts::{$file}.{$key}");
                $checked++;
            }
        }

        $this->assertGreaterThan(20, $checked);
    }

    public function test_translations_already_written_are_kept(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createFilamentResource('blog-posts', 'Post');
        $file = $this->tempModulesPath.'/blog-posts/lang/es/filament.php';
        $translations = include $file;
        $translations['post']['label'] = 'entrada';
        file_put_contents($file, '<?php return '.var_export($translations, true).';');

        $this->service->createWidget('blog-posts', 'Stats');

        $updated = include $file;
        $this->assertSame(['navigation_group', 'post', 'widgets'], array_keys($updated));
        $this->assertSame('entrada', $updated['post']['label']);
        $this->assertSame('Stats', $updated['widgets']['stats']['heading']);
    }

    public function test_resource_labels_follow_spanish_capitalization(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createFilamentResource('blog-posts', 'BlogPost');

        $translations = include $this->tempModulesPath.'/blog-posts/lang/es/filament.php';

        $this->assertSame('blog post', $translations['blog_post']['label']);
        $this->assertSame('Blog posts', $translations['blog_post']['plural_label']);
        $this->assertSame('Blog posts', $translations['navigation_group']);
    }

    public function test_api_routes_are_not_prefixed_twice(): void
    {
        $this->service->createModule('blog-posts');

        $routes = (string) file_get_contents($this->tempModulesPath.'/blog-posts/routes/api.php');

        $this->assertStringNotContainsString("prefix('api/", $routes);
        $this->assertStringNotContainsString("middleware('api')", $routes);
        $this->assertStringContainsString("Route::prefix('blog-posts')", $routes);
    }

    public function test_a_service_without_interface_implements_nothing(): void
    {
        $this->service->createModule('utils');
        $this->service->createService('utils', 'Logger', false);
        $this->service->createService('utils', 'Mailer', true);

        $logger = (string) file_get_contents($this->tempModulesPath.'/utils/src/Infrastructure/Services/LoggerService.php');
        $mailer = (string) file_get_contents($this->tempModulesPath.'/utils/src/Infrastructure/Services/MailerService.php');

        $this->assertStringNotContainsString('implements', $logger);
        $this->assertStringNotContainsString('ServiceInterface', $logger);
        $this->assertStringContainsString('final readonly class MailerService implements MailerServiceInterface', $mailer);
        $this->assertStringContainsString('use Modules\Utils\Application\Services\MailerServiceInterface;', $mailer);
    }

    public function test_the_policy_guards_the_model_the_entity_scaffold_writes_with_module_permission_keys(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createEntity('blog-posts', 'Post');
        $this->service->createFilamentResource('blog-posts', 'Post');

        $model = 'use Modules\BlogPosts\Infrastructure\Persistence\Eloquent\Models\PostModel;';
        $this->assertFileExists($this->tempModulesPath.'/blog-posts/src/Infrastructure/Persistence/Eloquent/Models/PostModel.php');
        $this->assertStringContainsString(
            'namespace Modules\BlogPosts\Infrastructure\Persistence\Eloquent\Models;',
            (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/Infrastructure/Persistence/Eloquent/Models/PostModel.php'),
        );
        $this->assertStringContainsString($model, (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/Infrastructure/Persistence/Eloquent/Repositories/EloquentPostRepository.php'));
        $this->assertStringContainsString($model, (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/Filament/Resources/PostResource.php'));

        $policy = (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/Policies/PostPolicy.php');
        $this->assertStringContainsString($model, $policy);
        foreach (['view_any', 'view', 'create', 'update', 'delete'] as $action) {
            $this->assertStringContainsString("'blog-posts:posts.{$action}'", $policy);
        }
        $this->assertDoesNotMatchRegularExpression("#authorize\(\\\$user, '[^':]+'\)#", $policy);
    }

    public function test_a_migration_for_an_existing_table_modifies_it(): void
    {
        $this->service->createModule('blog-posts');

        $result = $this->service->createMigration('blog-posts', 'add_slug_to_posts_table', 'blog_posts_posts', modify: true);

        $content = (string) file_get_contents($this->tempModulesPath.'/blog-posts/'.array_key_first($result->files));
        $this->assertStringContainsString("Schema::table('blog_posts_posts', function (Blueprint \$table): void {", $content);
        $this->assertStringNotContainsString('Schema::create', $content);
        $this->assertStringNotContainsString('dropIfExists', $content);
    }

    public function test_a_migration_for_a_new_table_creates_it(): void
    {
        $this->service->createModule('blog-posts');

        $result = $this->service->createMigration('blog-posts', 'create_posts_table');

        $content = (string) file_get_contents($this->tempModulesPath.'/blog-posts/'.array_key_first($result->files));
        $this->assertStringContainsString("Schema::create('blog_posts_posts'", $content);
        $this->assertStringContainsString("Schema::dropIfExists('blog_posts_posts')", $content);
    }

    public function test_controllers_render_inertia_pages_of_the_module(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createController('blog-posts', 'Post');
        $this->service->createController('blog-posts', 'Archive', 'invokable');
        $this->service->createController('blog-posts', 'Entry', 'resource');

        $controllers = $this->tempModulesPath.'/blog-posts/src/Http/Controllers';
        $default = (string) file_get_contents($controllers.'/PostController.php');
        $invokable = (string) file_get_contents($controllers.'/ArchiveController.php');
        $resource = (string) file_get_contents($controllers.'/EntryController.php');

        $this->assertStringContainsString("Inertia::render('BlogPosts/Post')", $default);
        $this->assertStringContainsString("Inertia::render('BlogPosts/Archive')", $invokable);
        foreach (['EntryIndex', 'EntryCreate', 'EntryShow', 'EntryEdit'] as $page) {
            $this->assertStringContainsString("Inertia::render('BlogPosts/{$page}'", $resource);
        }
        foreach ([$default, $invokable, $resource] as $content) {
            $this->assertStringNotContainsString('response()->view(', $content);
        }
        // The single request module:make-request generates for the same name
        $this->assertStringContainsString('use Modules\BlogPosts\Http\Requests\EntryRequest;', $resource);
        $this->assertStringNotContainsString('StoreEntryRequest', $resource);
    }

    public function test_the_widget_explains_how_it_reaches_the_dashboard(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createWidget('blog-posts', 'Stats');

        $widget = (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/Filament/Widgets/StatsWidget.php');

        $this->assertStringContainsString('public function registerFilamentWidgets(): array', $widget);
        $this->assertStringNotContainsString('auto-discovered', $widget);
        $this->assertStringNotContainsString('Livewire::component', $widget);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function generatedTestTypes(): array
    {
        return [
            'entity unit test' => ['unit', 'Post', 'tests/Unit/Domain/Entities/PostTest.php'],
            'DTO unit test' => ['unit-dto', 'PostDTO', 'tests/Unit/Application/DTOs/PostDTOTest.php'],
            'controller feature test' => ['feature', 'PostController', 'tests/Feature/Http/Controllers/PostControllerTest.php'],
            'Filament feature test' => ['feature-filament', 'Post', 'tests/Feature/Filament/PostResourceTest.php'],
        ];
    }

    #[DataProvider('generatedTestTypes')]
    public function test_every_generated_test_declares_the_class_its_file_is_named_after(string $type, string $name, string $path): void
    {
        $this->service->createModule('blog-posts');

        $result = $this->service->createTest('blog-posts', $name, $type);

        $this->assertSame(ScaffoldResultDTO::STATUS_CREATED, $result->files[$path] ?? null, implode(', ', array_keys($result->files)));
        $content = (string) file_get_contents($this->tempModulesPath.'/blog-posts/'.$path);
        $class = basename($path, '.php');
        $namespace = 'Modules\BlogPosts\\'.str_replace('/', '\\', ucfirst(dirname($path)));
        $this->assertStringContainsString("final class {$class} extends", $content);
        $this->assertStringContainsString("namespace {$namespace};", $content);
    }

    public function test_feature_tests_enable_the_module_on_the_module_test_case(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createTest('blog-posts', 'Post', 'feature');
        $this->service->createTest('blog-posts', 'Post', 'feature-filament');

        $controller = (string) file_get_contents($this->tempModulesPath.'/blog-posts/tests/Feature/Http/Controllers/PostControllerTest.php');
        $filament = (string) file_get_contents($this->tempModulesPath.'/blog-posts/tests/Feature/Filament/PostResourceTest.php');

        foreach ([$controller, $filament] as $content) {
            $this->assertStringContainsString('extends ModuleTestCase', $content);
            $this->assertStringContainsString("protected ?string \$moduleName = 'blog-posts';", $content);
            $this->assertStringContainsString('protected bool $autoEnableModule = true;', $content);
            $this->assertStringNotContainsString('RefreshDatabase', $content);
        }

        $this->assertStringContainsString("->component('BlogPosts/Post', false)", $controller);
        // A URL, not route(): route names registered after the application booted are not looked up
        $this->assertStringContainsString("\$this->get('/blog-posts/posts')", $controller);

        $this->assertStringContainsString('use InteractsWithModulePanel;', $filament);
        $this->assertStringContainsString('$this->registerOnAdminPanel([PostResource::class]);', $filament);
        $this->assertStringContainsString('$this->actingAsPanelAdmin();', $filament);
        $this->assertStringContainsString('use Modules\BlogPosts\Infrastructure\Persistence\Eloquent\Models\PostModel;', $filament);
        $this->assertStringNotContainsString('App\Domain\User', $filament);
    }

    public function test_a_page_lives_under_the_module_prefix_inside_the_site_layout(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createVuePage('blog-posts', 'Archive');

        $page = (string) file_get_contents($this->tempModulesPath.'/blog-posts/resources/js/pages/BlogPosts/Archive.vue');

        $this->assertStringContainsString("import DefaultLayout from '@/layouts/DefaultLayout.vue';", $page);
        $this->assertStringContainsString('<DefaultLayout>', $page);
        $this->assertStringContainsString("{{ \$t('blogPosts.title') }}", $page);
        $this->assertStringNotContainsString('bg-white', $page);
        $this->assertStringNotContainsString('text-gray-', $page);
    }

    public function test_a_component_interpolates_its_title_as_vue_expects(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createVueComponent('blog-posts', 'PostCard');

        $component = (string) file_get_contents($this->tempModulesPath.'/blog-posts/resources/js/components/PostCard.vue');

        $this->assertStringContainsString("{{ title ?? \$t('blogPosts.title') }}", $component);
        $this->assertStringNotContainsString("{{ '{{' }}", $component);
        $this->assertStringNotContainsString('<style', $component);
    }

    public function test_page_types_match_the_response_dto_keys(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createVuePage('blog-posts', 'Post');

        $types = (string) file_get_contents($this->tempModulesPath.'/blog-posts/resources/js/types/Post.ts');

        $this->assertStringContainsString('created_at: string;', $types);
        $this->assertStringContainsString('updated_at: string;', $types);
        $this->assertStringNotContainsString('createdAt', $types);
    }

    public function test_a_new_module_has_the_frontend_locales_its_pages_use(): void
    {
        $result = $this->service->createModule('blog-posts', displayName: 'Entradas del blog');

        foreach (['es', 'en'] as $locale) {
            $path = "resources/js/locales/{$locale}.ts";
            $this->assertSame(ScaffoldResultDTO::STATUS_CREATED, $result->files[$path] ?? null, $path);
            $content = (string) file_get_contents($this->tempModulesPath.'/blog-posts/'.$path);
            $this->assertStringContainsString('export default {', $content);
            $this->assertStringContainsString('blogPosts: {', $content);
        }
        $this->assertStringContainsString("title: 'Entradas del blog',", (string) file_get_contents($this->tempModulesPath.'/blog-posts/resources/js/locales/es.ts'));
    }

    public function test_an_entity_without_migration_says_how_to_create_its_table(): void
    {
        $this->service->createModule('blog-posts');

        $without = $this->service->createEntity('blog-posts', 'Post', false);
        $with = $this->service->createEntity('blog-posts', 'Comment', true);

        $this->assertStringContainsString('module:make-migration blog-posts create_posts_table', implode("\n", $without->nextSteps));
        $this->assertStringNotContainsString('module:make-migration', implode("\n", $with->nextSteps));
    }

    public function test_a_multi_word_resource_controller_redirects_to_the_routes_its_hint_registers(): void
    {
        $this->service->createModule('blog-posts');

        $result = $this->service->createController('blog-posts', 'BlogPost', 'resource');

        $controller = (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/Http/Controllers/BlogPostController.php');
        // Route::resource('blog-posts', ...) names its routes after the URI: "blog-posts.index"
        $this->assertSame(3, substr_count($controller, "->route('blog_posts.blog-posts.index')"));
        $this->assertStringNotContainsString('blog_posts.blog_posts.', $controller);
        $this->assertStringContainsString("Route::resource('blog-posts', BlogPostController::class)", implode("\n", $result->nextSteps));
    }

    public function test_generated_write_endpoints_authorize_and_hint_authenticated_routes(): void
    {
        $this->service->createModule('blog-posts');
        $resource = $this->service->createController('blog-posts', 'BlogPost', 'resource');
        $api = $this->service->createController('blog-posts', 'BlogPost', 'api');
        $this->service->createRequest('blog-posts', 'BlogPost');
        $this->service->createService('blog-posts', 'BlogPost');

        $controllers = $this->tempModulesPath.'/blog-posts/src/Http/Controllers';
        foreach ([$controllers.'/BlogPostController.php', $controllers.'/Api/BlogPostController.php'] as $path) {
            $content = (string) file_get_contents($path);
            $this->assertStringContainsString("Gate::authorize('create', BlogPostModel::class);", $content, $path);
            $this->assertStringContainsString("\$this->authorizeRecord('update', \$id);", $content, $path);
            $this->assertStringContainsString("\$this->authorizeRecord('delete', \$id);", $content, $path);
            // An id that is not a UUID, or no record, is a 404 rather than a 500
            $this->assertStringContainsString('Str::isUuid($id)', $content, $path);
        }

        $this->assertStringContainsString("->except(['index', 'show'])->middleware('auth');", implode("\n", $resource->nextSteps));
        $this->assertStringContainsString("->except(['index', 'show'])->middleware('auth');", implode("\n", $api->nextSteps));

        $request = (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/Http/Requests/BlogPostRequest.php');
        $this->assertStringNotContainsString('Authorization lives in the policy', $request);
        $this->assertStringContainsString('Gate::authorize', $request);

        $service = (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/Infrastructure/Services/BlogPostService.php');
        $this->assertStringContainsString('if (! Str::isUuid($id)) {', $service);
    }

    public function test_the_display_name_is_escaped_for_vue_i18n_in_the_locales(): void
    {
        $displayName = "Reservas @ sala | {norte} d'Or";

        $this->service->createModule('blog-posts', displayName: $displayName);

        $locale = (string) file_get_contents($this->tempModulesPath.'/blog-posts/resources/js/locales/es.ts');
        $this->assertStringContainsString("title: 'Reservas {\\'@\\'} sala {\\'|\\'} {\\'{\\'}norte{\\'}\\'} d\\'Or',", $locale);

        // Only the vue-i18n message is escaped
        $manifest = json_decode((string) file_get_contents($this->tempModulesPath.'/blog-posts/module.json'), true);
        $this->assertSame($displayName, $manifest['displayName']);
        $messages = include $this->tempModulesPath.'/blog-posts/lang/es/messages.php';
        $this->assertSame($displayName, $messages['title']);
    }

    public function test_page_prop_types_match_the_props_the_resource_controller_sends(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createVuePage('blog-posts', 'BlogPost');

        $types = (string) file_get_contents($this->tempModulesPath.'/blog-posts/resources/js/types/BlogPost.ts');

        $this->assertStringContainsString("export interface BlogPostListProps {\n    blogPosts: BlogPost[];\n}", $types);
        $this->assertStringContainsString("export interface BlogPostShowProps {\n    blogPost: BlogPost;\n}", $types);
    }

    public function test_the_generated_messages_file_has_nothing_a_merge_would_drop(): void
    {
        $this->service->createModule('blog-posts');

        $messages = (string) file_get_contents($this->tempModulesPath.'/blog-posts/lang/es/messages.php');

        $this->assertStringNotContainsString('/*', $messages);
        $this->assertStringNotContainsString('//', $messages);
    }

    public function test_generators_ask_for_the_labels_they_derive_from_class_names_to_be_translated(): void
    {
        $this->service->createModule('blog-posts');

        foreach ([
            $this->service->createFilamentResource('blog-posts', 'BlogPost'),
            $this->service->createWidget('blog-posts', 'Stats'),
            $this->service->createRelationManager('blog-posts', 'Comments', 'Event'),
        ] as $result) {
            $this->assertStringContainsString('Translate the labels written to lang/es/filament.php', implode("\n", $result->nextSteps), $result->message);
        }
    }

    public function test_the_controller_test_says_which_controllers_it_fits(): void
    {
        $this->service->createModule('blog-posts');

        $result = $this->service->createTest('blog-posts', 'Archive', 'feature');

        $this->assertStringContainsString('made without --resource or --invokable', implode("\n", $result->nextSteps));
    }

    public function test_a_module_cannot_take_over_a_core_page_prefix(): void
    {
        $pages = $this->tempModulesPath.'-pages';
        mkdir($pages.'/Events', 0755, true);
        touch($pages.'/Home.vue');
        $service = new ModuleScaffoldingService($this->stubRenderer, $this->tempModulesPath, $this->createStub(CoreVersionServiceInterface::class), $pages);

        try {
            foreach (['events', 'home'] as $name) {
                $result = $service->createModule($name);

                $this->assertTrue($result->isFailure(), $name);
                $this->assertStringContainsString('core page', implode(' ', $result->errors), $name);
                $this->assertDirectoryDoesNotExist($this->tempModulesPath.'/'.$name);
            }
        } finally {
            $this->deleteDirectory($pages);
        }
    }

    public function test_resource_route_hints_register_the_writes_before_the_reads(): void
    {
        $this->service->createModule('blog-posts');
        $result = $this->service->createController('blog-posts', 'BlogPost', 'resource');

        // Registered first, /blog-posts/create would be taken by the show route /blog-posts/{blog_post}
        $hint = implode("\n", $result->nextSteps);
        $writes = "Route::resource('blog-posts', BlogPostController::class)->except(['index', 'show'])->middleware('auth');";
        $reads = "Route::resource('blog-posts', BlogPostController::class)->only(['index', 'show']);";
        $controller = (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/Http/Controllers/BlogPostController.php');

        foreach ([$hint, $controller] as $text) {
            $this->assertNotFalse(strpos($text, $writes));
            $this->assertNotFalse(strpos($text, $reads));
            $this->assertLessThan(strpos($text, $reads), strpos($text, $writes));
        }
    }

    public function test_the_provider_does_not_claim_the_core_calls_enable_hooks(): void
    {
        $this->service->createModule('blog-posts');

        $provider = (string) file_get_contents($this->tempModulesPath.'/blog-posts/src/BlogPostsServiceProvider.php');

        $this->assertStringNotContainsString('onEnable', $provider);
        $this->assertStringNotContainsString('onDisable', $provider);
    }

    public function test_asset_build_steps_point_to_the_node_container_or_the_release_workflow(): void
    {
        $results = [
            $this->service->createModule('blog-posts'),
            $this->service->createVuePage('blog-posts', 'Archive'),
            $this->service->createVueComponent('blog-posts', 'PostCard'),
        ];

        foreach ($results as $result) {
            $steps = implode("\n", $result->nextSteps);
            $this->assertStringNotContainsString('module:build', $steps, $result->message);
            $this->assertStringContainsString('guildforge_node', $steps, $result->message);
            $this->assertStringContainsString('release workflow', $steps, $result->message);
        }
    }

    public function test_the_controller_feature_test_no_longer_says_route_cannot_find_module_routes(): void
    {
        $this->service->createModule('blog-posts');
        $this->service->createTest('blog-posts', 'Archive', 'feature');

        $test = (string) file_get_contents($this->tempModulesPath.'/blog-posts/tests/Feature/Http/Controllers/ArchiveControllerTest.php');

        $this->assertStringNotContainsString('not looked up', $test);
    }

    private function generateEveryTranslatedClass(string $module): void
    {
        $this->service->createModule($module);
        $this->service->createFilamentResource($module, 'Post');
        $this->service->createRelationManager($module, 'Comments', 'Event');
        $this->service->createWidget($module, 'Stats');
        $this->service->createEnum($module, 'PostStatus');
        $this->service->createNotification($module, 'PostPublished');
        $this->service->createController($module, 'Post');
        $this->service->createController($module, 'Entry', 'resource');
        $this->service->createController($module, 'Archive', 'invokable');
        $this->service->createRequest($module, 'Entry');
    }

    /**
     * @return array<string, string> Contents of every PHP file under the directory, by path
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), ['.', '..']);

        foreach ($items as $item) {
            $path = $dir.'/'.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
