<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleInstallerInterface;
use App\Application\Updates\Services\ModuleBackupServiceInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Modules\Exceptions\ModuleInstallationException;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModuleInstaller;
use App\Infrastructure\Modules\Services\ModuleMigrationRunner;
use App\Infrastructure\Modules\Services\ModuleSeederRunner;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use JsonException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use ZipArchive;

final class ModuleInstallerTest extends TestCase
{
    private ModuleInstaller $installer;

    private string $tempDir;

    private string $modulesPath;

    private MockInterface&ModuleRepositoryInterface $repository;

    private MockInterface&Dispatcher $dispatcher;

    private MockInterface&ModuleBackupServiceInterface $backupService;

    private ModuleMigrationRunner $migrationRunner;

    private ModuleSeederRunner $seederRunner;

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();

        $this->tempDir = storage_path('app/temp/modules-test');
        $this->modulesPath = storage_path('app/test-modules');

        File::makeDirectory($this->tempDir, 0755, true, true);
        File::makeDirectory($this->modulesPath, 0755, true, true);

        config(['modules.path' => $this->modulesPath]);

        $this->dispatcher = Mockery::mock(Dispatcher::class);
        $this->dispatcher->shouldReceive('dispatch')->andReturnNull()->byDefault();

        $this->repository = Mockery::mock(ModuleRepositoryInterface::class);
        $this->repository->shouldReceive('exists')->andReturn(false)->byDefault();

        $this->backupService = Mockery::mock(ModuleBackupServiceInterface::class);

        // Create real instances since they're final classes
        $this->migrationRunner = new ModuleMigrationRunner($this->modulesPath);
        $this->seederRunner = new ModuleSeederRunner($this->modulesPath);

        $this->installer = new ModuleInstaller(
            $this->dispatcher,
            $this->repository,
            $this->backupService,
            $this->migrationRunner,
            $this->seederRunner
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        File::deleteDirectory($this->modulesPath);
        File::deleteDirectory(public_path('build/modules'));

        parent::tearDown();
    }

    public function test_rejects_non_zip_content(): void
    {
        // Create a file that is not a valid ZIP (regardless of extension)
        $filePath = $this->tempDir.'/fake.zip';
        File::put($filePath, 'this is not zip content');

        $file = new UploadedFile($filePath, 'fake.zip', 'application/zip', null, true);

        $this->expectException(ModuleInstallationException::class);

        $this->installer->installFromZip($file);
    }

    public function test_validates_zip_size_limit(): void
    {
        // Create a fake zip file larger than 50MB
        $file = UploadedFile::fake()->create('module.zip', 51 * 1024); // 51MB in KB

        $this->expectException(ModuleInstallationException::class);

        $this->installer->installFromZip($file);
    }

    public function test_rejects_invalid_zip_file(): void
    {
        // Create an invalid zip file (just a text file with .zip extension)
        $filePath = $this->tempDir.'/invalid.zip';
        File::put($filePath, 'not a valid zip content');

        $file = new UploadedFile($filePath, 'invalid.zip', 'application/zip', null, true);

        $this->expectException(ModuleInstallationException::class);

        $this->installer->installFromZip($file);
    }

    public function test_rejects_zip_without_manifest(): void
    {
        $zipPath = $this->createValidZip([
            'src/SomeFile.php' => '<?php // some code',
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $this->expectException(ModuleInstallationException::class);

        $this->installer->installFromZip($file);
    }

    public function test_rejects_zip_with_invalid_manifest_json(): void
    {
        $zipPath = $this->createValidZip([
            'module.json' => 'not valid json {{{',
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $this->expectException(ModuleInstallationException::class);

        $this->installer->installFromZip($file);
    }

    public function test_rejects_zip_with_missing_manifest_fields(): void
    {
        $zipPath = $this->createValidZip([
            'module.json' => json_encode([
                'name' => 'test-module',
                // missing version, namespace, provider
            ]),
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $this->expectException(ModuleInstallationException::class);

        $this->installer->installFromZip($file);
    }

    public function test_rejects_duplicate_module_name(): void
    {
        // Create existing module directory
        File::makeDirectory($this->modulesPath.'/existing-module', 0755, true);

        // Module also exists in database — true duplicate
        $this->repository->shouldReceive('exists')
            ->with(Mockery::on(fn (ModuleName $n) => $n->value === 'existing-module'))
            ->andReturn(true);

        $zipPath = $this->createValidZip([
            'module.json' => json_encode([
                'name' => 'existing-module',
                'version' => '1.0.0',
                'namespace' => 'Modules\\ExistingModule',
                'provider' => 'Modules\\ExistingModule\\ExistingModuleServiceProvider',
            ]),
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $this->expectException(ModuleInstallationException::class);

        $this->installer->installFromZip($file);
    }

    public function test_cleans_up_leftover_directory_and_installs(): void
    {
        // Create leftover directory (from incomplete uninstall)
        $leftoverPath = $this->modulesPath.'/leftover-module';
        File::makeDirectory($leftoverPath, 0755, true);
        File::put($leftoverPath.'/module.json', json_encode(['name' => 'leftover-module']));

        // No DB record exists — this is a leftover
        $this->repository->shouldReceive('exists')
            ->with(Mockery::on(fn (ModuleName $n) => $n->value === 'leftover-module'))
            ->andReturn(false);

        $zipPath = $this->createValidZip([
            'module.json' => json_encode([
                'name' => 'leftover-module',
                'version' => '1.0.0',
                'namespace' => 'Modules\\LeftoverModule',
                'provider' => 'Modules\\LeftoverModule\\LeftoverModuleServiceProvider',
            ]),
            'src/LeftoverModuleServiceProvider.php' => '<?php namespace Modules\\LeftoverModule;',
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $manifest = $this->installer->installFromZip($file);

        $this->assertEquals('leftover-module', $manifest->name);
        $this->assertTrue(File::isDirectory($this->modulesPath.'/leftover-module'));
        $this->assertTrue(File::exists($this->modulesPath.'/leftover-module/module.json'));
    }

    public function test_rejects_forbidden_module_names(): void
    {
        foreach (ModuleInstallerInterface::FORBIDDEN_NAMES as $forbiddenName) {
            $zipPath = $this->createValidZip([
                'module.json' => json_encode([
                    'name' => $forbiddenName,
                    'version' => '1.0.0',
                    'namespace' => 'Modules\\'.ucfirst($forbiddenName),
                    'provider' => 'Modules\\'.ucfirst($forbiddenName).'\\ServiceProvider',
                ]),
            ]);

            $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

            try {
                $this->installer->installFromZip($file);
                $this->fail("Expected ModuleInstallationException for forbidden name: {$forbiddenName}");
            } catch (ModuleInstallationException) {
                $this->assertTrue(true);
            }

            // Cleanup for next iteration
            @unlink($zipPath);
        }
    }

    public function test_can_install_module_from_valid_zip(): void
    {
        $zipPath = $this->createValidZip([
            'module.json' => json_encode([
                'name' => 'valid-test-module',
                'version' => '1.0.0',
                'namespace' => 'Modules\\ValidTestModule',
                'provider' => 'Modules\\ValidTestModule\\ValidTestModuleServiceProvider',
                'description' => 'A valid test module',
                'author' => 'Test Author',
            ]),
            'src/ValidTestModuleServiceProvider.php' => '<?php namespace Modules\\ValidTestModule;',
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $manifest = $this->installer->installFromZip($file);

        $this->assertEquals('valid-test-module', $manifest->name);
        $this->assertEquals('1.0.0', $manifest->version);
        $this->assertTrue(File::isDirectory($this->modulesPath.'/valid-test-module'));
        $this->assertTrue(File::exists($this->modulesPath.'/valid-test-module/module.json'));
    }

    public function test_finds_manifest_in_subdirectory(): void
    {
        $zipPath = $this->createValidZip([
            'valid-test-module-2/module.json' => json_encode([
                'name' => 'valid-test-module-2',
                'version' => '2.0.0',
                'namespace' => 'Modules\\ValidTestModule2',
                'provider' => 'Modules\\ValidTestModule2\\ServiceProvider',
            ]),
            'valid-test-module-2/src/ServiceProvider.php' => '<?php',
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $manifest = $this->installer->installFromZip($file);

        $this->assertEquals('valid-test-module-2', $manifest->name);
        $this->assertTrue(File::isDirectory($this->modulesPath.'/valid-test-module-2'));
    }

    /**
     * @throws JsonException
     */
    public function test_can_install_module_when_move_directory_fails(): void
    {
        $zipPath = $this->createValidZip([
            'module.json' => json_encode([
                'name' => 'fallback-test-module',
                'version' => '1.0.0',
                'namespace' => 'Modules\\FallbackTestModule',
                'provider' => 'Modules\\FallbackTestModule\\FallbackTestModuleServiceProvider',
            ], JSON_THROW_ON_ERROR),
            'src/FallbackTestModuleServiceProvider.php' => '<?php namespace Modules\\FallbackTestModule;',
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        // Store the real File facade implementation
        $realFilesystem = File::getFacadeRoot();

        // Create a partial mock that intercepts specific methods
        File::partialMock()
            ->shouldReceive('moveDirectory')
            ->once()
            ->andReturn(false) // Simulate move failure
            ->shouldReceive('copyDirectory')
            ->once()
            ->andReturnUsing(fn ($source, $target) => $realFilesystem->copyDirectory($source, $target))
            ->shouldReceive('deleteDirectory')
            ->atLeast()
            ->times(2) // At least: once for source after copy, once for temp cleanup (tearDown adds more)
            ->andReturnUsing(fn ($path) => $realFilesystem->deleteDirectory($path));

        $manifest = $this->installer->installFromZip($file);

        $this->assertEquals('fallback-test-module', $manifest->name);
        $this->assertEquals('1.0.0', $manifest->version);
        $this->assertTrue($realFilesystem->isDirectory($this->modulesPath.'/fallback-test-module'));
        $this->assertTrue($realFilesystem->exists($this->modulesPath.'/fallback-test-module/module.json'));
    }

    public function test_can_update_existing_module_from_zip(): void
    {
        // Create existing module directory
        $moduleDir = $this->modulesPath.'/update-test-module';
        File::makeDirectory($moduleDir, 0755, true);
        File::put($moduleDir.'/old-file.txt', 'old content');

        // Create a real module entity
        $module = new Module(
            id: ModuleId::generate(),
            name: new ModuleName('update-test-module'),
            displayName: 'Update Test Module',
            description: 'Test module for updates',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test Author',
            requirements: new ModuleRequirements(
                phpVersion: null,
                laravelVersion: null,
                requiredModules: [],
                requiredExtensions: []
            ),
            status: ModuleStatus::Enabled,
            path: $moduleDir
        );

        $this->repository->shouldReceive('findByName')
            ->with(Mockery::type(ModuleName::class))
            ->andReturn($module);

        $this->repository->shouldReceive('save')
            ->once()
            ->with($module);

        $backupPath = $this->tempDir.'/backup.zip';
        $this->backupService->shouldReceive('createBackup')
            ->once()
            ->with(Mockery::type(ModuleName::class))
            ->andReturn($backupPath);

        // Migration/seeder runners are real instances, they'll run silently in test mode

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(ModuleUpdated::class));

        $zipPath = $this->createValidZip([
            'module.json' => json_encode([
                'name' => 'update-test-module',
                'version' => '2.0.0',
                'namespace' => 'Modules\\UpdateTestModule',
                'provider' => 'Modules\\UpdateTestModule\\ServiceProvider',
            ]),
            'src/NewFile.php' => '<?php // new content',
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $manifest = $this->installer->updateFromZip($file);

        $this->assertEquals('update-test-module', $manifest->name);
        $this->assertEquals('2.0.0', $manifest->version);
        $this->assertTrue(File::exists($moduleDir.'/src/NewFile.php'));
        $this->assertFalse(File::exists($moduleDir.'/old-file.txt'));
    }

    public function test_update_rejects_non_existent_module(): void
    {
        $this->repository->shouldReceive('findByName')
            ->with(Mockery::type(ModuleName::class))
            ->andReturn(null);

        $zipPath = $this->createValidZip([
            'module.json' => json_encode([
                'name' => 'non-existent-module',
                'version' => '1.0.0',
                'namespace' => 'Modules\\NonExistent',
                'provider' => 'Modules\\NonExistent\\ServiceProvider',
            ]),
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $this->expectException(ModuleInstallationException::class);

        $this->installer->updateFromZip($file);
    }

    public function test_update_restores_backup_on_failure(): void
    {
        // Create existing module directory
        $moduleDir = $this->modulesPath.'/failing-module';
        File::makeDirectory($moduleDir, 0755, true);
        File::put($moduleDir.'/important.txt', 'important data');

        // Create a real module entity
        $module = new Module(
            id: ModuleId::generate(),
            name: new ModuleName('failing-module'),
            displayName: 'Failing Module',
            description: 'Test module for failure scenarios',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test Author',
            requirements: new ModuleRequirements(
                phpVersion: null,
                laravelVersion: null,
                requiredModules: [],
                requiredExtensions: []
            ),
            status: ModuleStatus::Enabled,
            path: $moduleDir
        );

        $this->repository->shouldReceive('findByName')
            ->with(Mockery::type(ModuleName::class))
            ->andReturn($module);

        // First save succeeds, but we'll simulate a failure after that
        $this->repository->shouldReceive('save')
            ->once()
            ->with($module)
            ->andThrow(new \RuntimeException('Database failure'));

        // Second save is for restoration
        $this->repository->shouldReceive('save')
            ->once()
            ->with($module);

        $backupPath = $this->tempDir.'/backup.zip';
        $this->backupService->shouldReceive('createBackup')
            ->once()
            ->with(Mockery::type(ModuleName::class))
            ->andReturn($backupPath);

        $this->backupService->shouldReceive('restoreBackup')
            ->once()
            ->with(Mockery::type(ModuleName::class), $backupPath);

        $zipPath = $this->createValidZip([
            'module.json' => json_encode([
                'name' => 'failing-module',
                'version' => '2.0.0',
                'namespace' => 'Modules\\FailingModule',
                'provider' => 'Modules\\FailingModule\\ServiceProvider',
            ]),
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $this->expectException(ModuleInstallationException::class);

        $this->installer->updateFromZip($file);
    }

    public function test_peek_manifest_returns_manifest_without_installing(): void
    {
        $zipPath = $this->createValidZip([
            'module.json' => json_encode([
                'name' => 'peek-test-module',
                'version' => '1.5.0',
                'namespace' => 'Modules\\PeekTest',
                'provider' => 'Modules\\PeekTest\\ServiceProvider',
                'description' => 'Test peeking',
                'author' => 'Test Author',
            ]),
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $manifest = $this->installer->peekManifest($file);

        $this->assertEquals('peek-test-module', $manifest->name);
        $this->assertEquals('1.5.0', $manifest->version);
        $this->assertEquals('Modules\\PeekTest', $manifest->namespace);

        // Verify module was NOT installed
        $this->assertFalse(File::isDirectory($this->modulesPath.'/peek-test-module'));
    }

    public function test_module_exists_returns_true_for_existing_module(): void
    {
        $this->repository->shouldReceive('exists')
            ->with(Mockery::on(fn (ModuleName $n) => $n->value === 'existing-module'))
            ->andReturn(true);

        $exists = $this->installer->moduleExists('existing-module');

        $this->assertTrue($exists);
    }

    public function test_module_exists_returns_false_for_non_existing_module(): void
    {
        $this->repository->shouldReceive('exists')
            ->with(Mockery::on(fn (ModuleName $n) => $n->value === 'non-existing-module'))
            ->andReturn(false);

        $exists = $this->installer->moduleExists('non-existing-module');

        $this->assertFalse($exists);
    }

    public function test_install_publishes_pre_built_assets(): void
    {
        // Create a ZIP with pre-built assets
        $zipPath = $this->createValidZip([
            'module.json' => json_encode([
                'name' => 'assets-test-module',
                'version' => '1.0.0',
                'namespace' => 'Modules\\AssetsTest',
                'provider' => 'Modules\\AssetsTest\\ServiceProvider',
            ]),
            'public/build/app.js' => 'console.log("module app");',
            'public/build/app.css' => '.module { color: red; }',
        ]);

        $file = new UploadedFile($zipPath, 'module.zip', 'application/zip', null, true);

        $manifest = $this->installer->installFromZip($file);

        $this->assertEquals('assets-test-module', $manifest->name);

        // Verify assets were published to public/build/modules/{name}/
        $publicBuildPath = public_path('build/modules/assets-test-module');
        $this->assertTrue(File::isDirectory($publicBuildPath));
        $this->assertTrue(File::exists($publicBuildPath.'/app.js'));
        $this->assertTrue(File::exists($publicBuildPath.'/app.css'));
        $this->assertEquals('console.log("module app");', File::get($publicBuildPath.'/app.js'));

        // Verify source build directory was removed from module
        $moduleBuildPath = $this->modulesPath.'/assets-test-module/public/build';
        $this->assertFalse(File::isDirectory($moduleBuildPath));
    }

    /**
     * @param  array<string, string>  $files
     */
    private function createValidZip(array $files): string
    {
        $zipPath = $this->tempDir.'/test-module-'.uniqid().'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $zipPath;
    }
}
