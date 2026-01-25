<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleInstallerInterface;
use App\Domain\Modules\Exceptions\ModuleInstallationException;
use App\Infrastructure\Modules\Services\ModuleInstaller;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;
use ZipArchive;

final class ModuleInstallerTest extends TestCase
{
    private ModuleInstaller $installer;

    private string $tempDir;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('app/temp/modules-test');
        $this->modulesPath = storage_path('app/test-modules');

        File::makeDirectory($this->tempDir, 0755, true, true);
        File::makeDirectory($this->modulesPath, 0755, true, true);

        config(['modules.path' => $this->modulesPath]);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturnNull();

        $this->installer = new ModuleInstaller($dispatcher);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        File::deleteDirectory($this->modulesPath);

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
     * @param  array<string, string>  $files
     */
    private function createValidZip(array $files): string
    {
        $zipPath = $this->tempDir.'/test-module-'.uniqid().'.zip';

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $zipPath;
    }
}
