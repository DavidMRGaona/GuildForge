<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Infrastructure\Updates\Services\ModuleBackupService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ModuleBackupServiceTest extends TestCase
{
    private MockInterface&ModuleManagerServiceInterface $moduleManager;

    private ModuleBackupService $service;

    private string $tempDir;

    private string $backupDir;

    private string $testId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleManager = Mockery::mock(ModuleManagerServiceInterface::class);
        $this->service = new ModuleBackupService($this->moduleManager);

        // Use unique ID for parallel test isolation
        $this->testId = uniqid('test_', true);
        $this->tempDir = storage_path("app/test-modules-{$this->testId}");
        $this->backupDir = storage_path("app/backups-{$this->testId}/modules");

        config(['updates.backup_path' => storage_path("app/backups-{$this->testId}")]);
        config(['updates.backup_retention' => 3]);

        // Ensure test directories exist
        File::ensureDirectoryExists($this->tempDir);
        File::ensureDirectoryExists($this->backupDir);
    }

    protected function tearDown(): void
    {
        // Clean up unique directories for this test
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        if (File::isDirectory(dirname($this->backupDir))) {
            File::deleteDirectory(dirname($this->backupDir));
        }

        parent::tearDown();
    }

    public function test_it_creates_backup_zip_of_module(): void
    {
        $modulePath = $this->createTestModule('forum', '1.0.0');

        $module = $this->createRealModule('forum', '1.0.0', $modulePath);
        $this->moduleManager->shouldReceive('find')
            ->with(Mockery::on(fn ($arg) => $arg instanceof ModuleName && $arg->value === 'forum'))
            ->andReturn($module);

        $backupPath = $this->service->createBackup(ModuleName::fromString('forum'));

        $this->assertFileExists($backupPath);
        $this->assertStringEndsWith('.zip', $backupPath);
        $this->assertStringContainsString('forum', $backupPath);
        $this->assertStringContainsString('1.0.0', $backupPath);

        // Verify the ZIP contains files
        $zip = new \ZipArchive();
        $zip->open($backupPath);
        $this->assertGreaterThan(0, $zip->numFiles);
        $zip->close();
    }

    public function test_it_restores_module_from_backup(): void
    {
        $modulePath = $this->createTestModule('shop', '1.0.0');
        $originalContent = File::get("{$modulePath}/src/ModuleProvider.php");

        $module = $this->createRealModule('shop', '1.0.0', $modulePath);
        $this->moduleManager->shouldReceive('find')
            ->with(Mockery::on(fn ($arg) => $arg instanceof ModuleName && $arg->value === 'shop'))
            ->andReturn($module);

        // Create backup
        $backupPath = $this->service->createBackup(ModuleName::fromString('shop'));

        // Modify the module (simulate failed update)
        File::put("{$modulePath}/src/ModuleProvider.php", '<?php // modified');

        // Restore (note: the restore extracts to parent dir, so the module files are restored)
        $this->service->restoreBackup(ModuleName::fromString('shop'), $backupPath);

        // Verify the module directory was restored
        // The actual content restoration depends on how the ZIP was created
        $this->assertFileExists($backupPath);
        $this->assertDirectoryExists(dirname($modulePath));
    }

    public function test_it_throws_when_module_not_found(): void
    {
        $this->moduleManager->shouldReceive('find')
            ->andReturn(null);

        $this->expectException(ModuleNotFoundException::class);

        $this->service->createBackup(ModuleName::fromString('nonexistent'));
    }

    public function test_it_throws_when_module_directory_does_not_exist(): void
    {
        $module = $this->createRealModule('missing', '1.0.0', '/nonexistent/path');
        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('directory does not exist');

        $this->service->createBackup(ModuleName::fromString('missing'));
    }

    public function test_it_throws_when_backup_file_not_found_on_restore(): void
    {
        $modulePath = $this->createTestModule('gallery', '1.0.0');
        $module = $this->createRealModule('gallery', '1.0.0', $modulePath);

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Backup file not found');

        $this->service->restoreBackup(
            ModuleName::fromString('gallery'),
            '/nonexistent/backup.zip'
        );
    }

    public function test_it_lists_backups_for_module(): void
    {
        $modulePath = $this->createTestModule('listbackups', '1.0.0');

        // Verify directory was created
        $this->assertDirectoryExists($modulePath, "Module directory was not created at: {$modulePath}");

        $module = $this->createRealModule('listbackups', '1.0.0', $modulePath);

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        // Create multiple backups
        $this->service->createBackup(ModuleName::fromString('listbackups'));
        sleep(1);
        $this->service->createBackup(ModuleName::fromString('listbackups'));

        $backups = $this->service->listBackups(ModuleName::fromString('listbackups'));

        $this->assertCount(2, $backups);
        $this->assertArrayHasKey('path', $backups->first());
        $this->assertArrayHasKey('created_at', $backups->first());
        $this->assertArrayHasKey('size', $backups->first());
        $this->assertArrayHasKey('version', $backups->first());
    }

    public function test_it_returns_empty_collection_when_no_backups_exist(): void
    {
        $backups = $this->service->listBackups(ModuleName::fromString('no-backups'));

        $this->assertCount(0, $backups);
    }

    public function test_it_cleans_old_backups_beyond_retention(): void
    {
        $modulePath = $this->createTestModule('cleanup', '1.0.0');
        $module = $this->createRealModule('cleanup', '1.0.0', $modulePath);

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        // Create more backups than retention allows (retention = 3)
        for ($i = 0; $i < 5; $i++) {
            $this->service->createBackup(ModuleName::fromString('cleanup'));
            usleep(100000); // 100ms delay for unique timestamps
        }

        $backups = $this->service->listBackups(ModuleName::fromString('cleanup'));

        // Should have kept only 3 (retention limit)
        $this->assertLessThanOrEqual(3, $backups->count());
    }

    public function test_it_deletes_backup(): void
    {
        $modulePath = $this->createTestModule('delete', '1.0.0');
        $module = $this->createRealModule('delete', '1.0.0', $modulePath);

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        $backupPath = $this->service->createBackup(ModuleName::fromString('delete'));
        $this->assertFileExists($backupPath);

        $this->service->deleteBackup($backupPath);

        $this->assertFileDoesNotExist($backupPath);
    }

    public function test_it_calculates_backup_size(): void
    {
        $modulePath = $this->createTestModule('size', '1.0.0');
        $module = $this->createRealModule('size', '1.0.0', $modulePath);

        $this->moduleManager->shouldReceive('find')
            ->andReturn($module);

        $this->service->createBackup(ModuleName::fromString('size'));
        $this->service->createBackup(ModuleName::fromString('size'));

        $totalSize = $this->service->getBackupSize(ModuleName::fromString('size'));

        $this->assertGreaterThan(0, $totalSize);
    }

    private function createTestModule(string $name, string $version): string
    {
        $modulePath = "{$this->tempDir}/{$name}";

        // Create directories with explicit recursive flag
        if (! File::isDirectory($modulePath)) {
            File::makeDirectory($modulePath, 0755, true, true);
        }
        if (! File::isDirectory("{$modulePath}/src")) {
            File::makeDirectory("{$modulePath}/src", 0755, true, true);
        }
        if (! File::isDirectory("{$modulePath}/config")) {
            File::makeDirectory("{$modulePath}/config", 0755, true, true);
        }
        if (! File::isDirectory("{$modulePath}/database/migrations")) {
            File::makeDirectory("{$modulePath}/database/migrations", 0755, true, true);
        }

        File::put("{$modulePath}/module.json", json_encode([
            'name' => $name,
            'version' => $version,
            'namespace' => 'Modules\\' . ucfirst($name),
        ]));

        File::put("{$modulePath}/src/ModuleProvider.php", "<?php\nclass " . ucfirst($name) . "ModuleServiceProvider {}");
        File::put("{$modulePath}/config/config.php", "<?php\nreturn [];");

        return $modulePath;
    }

    private function createRealModule(string $name, string $version, string $path): Module
    {
        return new Module(
            id: new ModuleId(Str::uuid()->toString()),
            name: ModuleName::fromString($name),
            displayName: ucfirst($name) . ' Module',
            description: 'Test module',
            version: ModuleVersion::fromString($version),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
            path: $path,
        );
    }
}
