<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleInstallerInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Updates\Services\ModuleBackupServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;
use ZipArchive;

/**
 * Updating a module from a ZIP replaces its whole directory, release config/settings.php
 * included: the values saved in the panel must come through untouched.
 */
final class ModuleInstallerSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $tempDir;

    private string $modulesPath;

    private string $publicPath;

    protected function setUp(): void
    {
        parent::setUp();

        // public_path() reads the application's public path: keep parallel runs off public/build/modules
        $this->publicPath = sys_get_temp_dir().'/gf-public-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->publicPath.'/build/modules');
        $this->app->usePublicPath($this->publicPath);

        $this->tempDir = sys_get_temp_dir().'/gf-zip-settings-'.bin2hex(random_bytes(8));
        $this->modulesPath = $this->tempDir.'/modules';
        File::ensureDirectoryExists("{$this->modulesPath}/zip-mod/config");
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        File::deleteDirectory($this->publicPath);
        parent::tearDown();
    }

    public function test_update_from_zip_keeps_panel_settings(): void
    {
        File::put("{$this->modulesPath}/zip-mod/module.json", (string) json_encode(['name' => 'zip-mod', 'version' => '1.0.0', 'namespace' => 'Modules\\ZipMod', 'provider' => 'ZipModServiceProvider', 'requires' => ['core' => '>=2.0']]));
        File::put("{$this->modulesPath}/zip-mod/config/settings.php", "<?php\n\nreturn ['a' => 1];\n");
        ModuleModel::factory()->enabled()->create([
            'name' => 'zip-mod',
            'version' => '1.0.0',
            'path' => "{$this->modulesPath}/zip-mod",
            'namespace' => 'Modules\\ZipMod',
            'provider' => 'ZipModServiceProvider',
            'installed_at' => now(),
        ]);
        app(ModuleManagerServiceInterface::class)->updateSettings(ModuleName::fromString('zip-mod'), ['a' => 2]);
        $backup = Mockery::mock(ModuleBackupServiceInterface::class);
        $backup->shouldReceive('createBackup')->andReturn($this->tempDir.'/backup.zip');
        $this->app->instance(ModuleBackupServiceInterface::class, $backup);

        app(ModuleInstallerInterface::class)->updateFromZip($this->release([
            'module.json' => (string) json_encode(['name' => 'zip-mod', 'version' => '1.1.0', 'namespace' => 'Modules\\ZipMod', 'provider' => 'ZipModServiceProvider', 'requires' => ['core' => '>=2.0']]),
            'config/settings.php' => "<?php\n\nreturn ['a' => 1, 'b' => true];\n",
        ]));

        $this->assertSame(['a' => 2, 'b' => true], app(ModuleSettingsStoreInterface::class)->effective('zip-mod', "{$this->modulesPath}/zip-mod"));
    }

    /**
     * @param  array<string, string>  $files
     */
    private function release(array $files): UploadedFile
    {
        $zipPath = $this->tempDir.'/zip-mod.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return new UploadedFile($zipPath, 'zip-mod.zip', 'application/zip', null, true);
    }
}
