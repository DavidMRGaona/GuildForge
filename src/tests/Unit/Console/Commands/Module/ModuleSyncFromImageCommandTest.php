<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\Module;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class ModuleSyncFromImageCommandTest extends TestCase
{
    private string $imagePath;

    private string $volumePath;

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();

        $this->imagePath = storage_path('app/test-image-modules');
        $this->volumePath = storage_path('app/test-volume-modules');

        File::makeDirectory($this->imagePath, 0755, true, true);
        File::makeDirectory($this->volumePath, 0755, true, true);

        config(['modules.path' => $this->volumePath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->imagePath);
        File::deleteDirectory($this->volumePath);
        File::deleteDirectory(public_path('build/modules'));

        parent::tearDown();
    }

    public function test_copies_module_from_image_on_first_deploy(): void
    {
        // Create a module in the image path
        $imageModulePath = $this->imagePath.'/new-module';
        File::makeDirectory($imageModulePath.'/src', 0755, true);
        File::put($imageModulePath.'/module.json', json_encode([
            'name' => 'new-module',
            'version' => '1.0.0',
            'namespace' => 'Modules\\NewModule',
            'provider' => 'Modules\\NewModule\\ServiceProvider',
        ]));
        File::put($imageModulePath.'/src/ServiceProvider.php', '<?php namespace Modules\\NewModule;');

        // Run the command
        $this->artisan('module:sync-from-image', ['path' => $this->imagePath])
            ->assertExitCode(0);

        // Verify module was copied to volume
        $volumeModulePath = $this->volumePath.'/new-module';
        $this->assertTrue(File::isDirectory($volumeModulePath));
        $this->assertTrue(File::exists($volumeModulePath.'/module.json'));
        $this->assertTrue(File::exists($volumeModulePath.'/src/ServiceProvider.php'));
    }

    public function test_keeps_volume_module_when_version_equal_or_newer(): void
    {
        // Create module in image with version 1.0.0
        $imageModulePath = $this->imagePath.'/keep-module';
        File::makeDirectory($imageModulePath, 0755, true);
        File::put($imageModulePath.'/module.json', json_encode([
            'name' => 'keep-module',
            'version' => '1.0.0',
        ]));
        File::put($imageModulePath.'/image-file.txt', 'image content');

        // Create module in volume with version 1.5.0 (newer)
        $volumeModulePath = $this->volumePath.'/keep-module';
        File::makeDirectory($volumeModulePath, 0755, true);
        File::put($volumeModulePath.'/module.json', json_encode([
            'name' => 'keep-module',
            'version' => '1.5.0',
        ]));
        File::put($volumeModulePath.'/volume-file.txt', 'volume content');

        // Run the command
        $this->artisan('module:sync-from-image', ['path' => $this->imagePath])
            ->assertExitCode(0);

        // Verify volume version is kept
        $this->assertTrue(File::exists($volumeModulePath.'/volume-file.txt'));
        $this->assertFalse(File::exists($volumeModulePath.'/image-file.txt'));

        $manifest = json_decode(File::get($volumeModulePath.'/module.json'), true);
        $this->assertEquals('1.5.0', $manifest['version']);
    }

    public function test_overwrites_volume_module_when_image_has_newer_version(): void
    {
        // Create module in image with version 2.0.0
        $imageModulePath = $this->imagePath.'/update-module';
        File::makeDirectory($imageModulePath, 0755, true);
        File::put($imageModulePath.'/module.json', json_encode([
            'name' => 'update-module',
            'version' => '2.0.0',
        ]));
        File::put($imageModulePath.'/new-feature.txt', 'new feature');

        // Create module in volume with version 1.0.0 (older)
        $volumeModulePath = $this->volumePath.'/update-module';
        File::makeDirectory($volumeModulePath, 0755, true);
        File::put($volumeModulePath.'/module.json', json_encode([
            'name' => 'update-module',
            'version' => '1.0.0',
        ]));
        File::put($volumeModulePath.'/old-file.txt', 'old content');

        // Run the command
        $this->artisan('module:sync-from-image', ['path' => $this->imagePath])
            ->assertExitCode(0);

        // Verify image version overwrote volume
        $this->assertTrue(File::exists($volumeModulePath.'/new-feature.txt'));
        $this->assertFalse(File::exists($volumeModulePath.'/old-file.txt'));

        $manifest = json_decode(File::get($volumeModulePath.'/module.json'), true);
        $this->assertEquals('2.0.0', $manifest['version']);
    }

    public function test_handles_missing_image_path_gracefully(): void
    {
        $nonExistentPath = storage_path('app/non-existent-path');

        // Run the command with non-existent path
        // The command should handle missing path without crashing
        $this->artisan('module:sync-from-image', ['path' => $nonExistentPath]);

        // If we reach here without exception, the test passes
        $this->assertTrue(true);
    }

    public function test_copies_pre_built_assets_from_synced_modules(): void
    {
        // Create module in image with pre-built assets
        $imageModulePath = $this->imagePath.'/assets-module';
        File::makeDirectory($imageModulePath.'/public/build', 0755, true);
        File::put($imageModulePath.'/module.json', json_encode([
            'name' => 'assets-module',
            'version' => '1.0.0',
        ]));
        File::put($imageModulePath.'/public/build/app.js', 'console.log("assets");');
        File::put($imageModulePath.'/public/build/app.css', '.assets { color: blue; }');

        // Run the command
        $this->artisan('module:sync-from-image', ['path' => $this->imagePath])
            ->assertExitCode(0);

        // Verify module was copied
        $volumeModulePath = $this->volumePath.'/assets-module';
        $this->assertTrue(File::isDirectory($volumeModulePath));

        // Verify pre-built assets were published to public/build/modules/{name}/
        $publicBuildPath = public_path('build/modules/assets-module');
        $this->assertTrue(File::isDirectory($publicBuildPath));
        $this->assertTrue(File::exists($publicBuildPath.'/app.js'));
        $this->assertTrue(File::exists($publicBuildPath.'/app.css'));
        $this->assertEquals('console.log("assets");', File::get($publicBuildPath.'/app.js'));
    }
}
