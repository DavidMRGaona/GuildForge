<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleMigrationAnalyzerInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Exceptions\ModuleMigrationViolationException;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\CoreTableRegistry;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModuleMigrationRunner;
use App\Infrastructure\Modules\Services\ModuleSchemaGuard;
use PHPUnit\Framework\TestCase;

final class ModuleMigrationRunnerTest extends TestCase
{
    private ModuleMigrationRunner $runner;

    private string $testModulesPath;

    private ModuleMigrationAnalyzerInterface $analyzer;

    private ModuleSchemaGuard $schemaGuard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testModulesPath = sys_get_temp_dir().'/guildforge_test_modules_migrations_'.uniqid();
        $this->analyzer = $this->createMock(ModuleMigrationAnalyzerInterface::class);
        $this->schemaGuard = new ModuleSchemaGuard(new CoreTableRegistry);
        $this->runner = new ModuleMigrationRunner(
            $this->testModulesPath,
            $this->analyzer,
            $this->schemaGuard,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testModulesPath);

        parent::tearDown();
    }

    public function test_it_runs_migrations_for_module(): void
    {
        // Arrange
        $module = $this->createModule('test-module', '1.0.0');
        $moduleDir = $this->testModulesPath.'/test-module';
        $migrationsDir = $moduleDir.'/database/migrations';

        mkdir($migrationsDir, 0755, true);

        // Create a sample migration file
        $migrationFile = $migrationsDir.'/2024_01_01_000000_create_test_table.php';
        file_put_contents($migrationFile, '<?php // Migration');

        // Act
        $count = $this->runner->run($module);

        // Assert
        $this->assertIsInt($count);
        $this->assertGreaterThan(0, $count);
    }

    public function test_it_returns_count_of_migrations_run(): void
    {
        // Arrange
        $module = $this->createModule('multi-migration-module', '1.0.0');
        $moduleDir = $this->testModulesPath.'/multi-migration-module';
        $migrationsDir = $moduleDir.'/database/migrations';

        mkdir($migrationsDir, 0755, true);

        // Create multiple migration files
        file_put_contents(
            $migrationsDir.'/2024_01_01_000000_create_users_table.php',
            '<?php // Migration 1'
        );
        file_put_contents(
            $migrationsDir.'/2024_01_02_000000_create_posts_table.php',
            '<?php // Migration 2'
        );
        file_put_contents(
            $migrationsDir.'/2024_01_03_000000_create_comments_table.php',
            '<?php // Migration 3'
        );

        // Act
        $count = $this->runner->run($module);

        // Assert
        $this->assertEquals(3, $count);
    }

    public function test_it_returns_zero_when_no_migrations(): void
    {
        // Arrange
        $module = $this->createModule('no-migrations-module', '1.0.0');
        $moduleDir = $this->testModulesPath.'/no-migrations-module';

        mkdir($moduleDir, 0755, true);
        // Don't create migrations directory

        // Act
        $count = $this->runner->run($module);

        // Assert
        $this->assertEquals(0, $count);
    }

    public function test_it_throws_exception_when_module_not_found(): void
    {
        // Arrange
        $module = $this->createModule('nonexistent-module', '1.0.0');

        // Don't create module directory

        // Assert
        $this->expectException(ModuleNotFoundException::class);

        // Act
        $this->runner->run($module);
    }

    public function test_it_calls_analyzer_before_running_migrations(): void
    {
        // Arrange
        $module = $this->createModule('analyzed-module', '1.0.0');
        $moduleDir = $this->testModulesPath.'/analyzed-module';
        $migrationsDir = $moduleDir.'/database/migrations';

        mkdir($migrationsDir, 0755, true);
        file_put_contents(
            $migrationsDir.'/2024_01_01_000000_create_analyzed_table.php',
            '<?php // Migration'
        );

        // Assert
        $this->analyzer->expects($this->once())
            ->method('analyzeMigrations')
            ->with('analyzed-module', $migrationsDir);

        // Act
        $this->runner->run($module);
    }

    public function test_it_throws_when_analyzer_detects_violation(): void
    {
        // Arrange
        $module = $this->createModule('violating-module', '1.0.0');
        $moduleDir = $this->testModulesPath.'/violating-module';
        $migrationsDir = $moduleDir.'/database/migrations';

        mkdir($migrationsDir, 0755, true);
        file_put_contents(
            $migrationsDir.'/2024_01_01_000000_drop_core_table.php',
            '<?php // Violating migration'
        );

        $this->analyzer->method('analyzeMigrations')
            ->willThrowException(
                ModuleMigrationViolationException::withViolations(
                    'violating-module',
                    '2024_01_01_000000_drop_core_table.php',
                    ['Migration attempts to modify core table'],
                )
            );

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->runner->run($module);
    }

    public function test_it_does_not_suppress_violation_exception(): void
    {
        // Arrange
        $module = $this->createModule('sneaky-module', '1.0.0');
        $moduleDir = $this->testModulesPath.'/sneaky-module';
        $migrationsDir = $moduleDir.'/database/migrations';

        mkdir($migrationsDir, 0755, true);
        file_put_contents(
            $migrationsDir.'/2024_01_01_000000_alter_users_table.php',
            '<?php // Sneaky migration'
        );

        $this->analyzer->method('analyzeMigrations')
            ->willThrowException(
                ModuleMigrationViolationException::withViolations(
                    'sneaky-module',
                    '2024_01_01_000000_alter_users_table.php',
                    ['Schema::table on core table users'],
                )
            );

        // Act & Assert - ensure the exception propagates and is not caught
        try {
            $this->runner->run($module);
            $this->fail('Expected ModuleMigrationViolationException was not thrown');
        } catch (ModuleMigrationViolationException $e) {
            $this->assertStringContainsString('sneaky-module', $e->getMessage());
            $this->assertSame('sneaky-module', $e->moduleName);
        }
    }

    private function createModule(string $name, string $version): Module
    {
        return new Module(
            id: ModuleId::generate(),
            name: new ModuleName($name),
            displayName: ucfirst($name),
            description: "Test module: {$name}",
            version: ModuleVersion::fromString($version),
            author: 'Test Author',
            requirements: new ModuleRequirements(
                phpVersion: null,
                laravelVersion: null,
                requiredModules: [],
                requiredExtensions: []
            ),
            status: ModuleStatus::Disabled,
            enabledAt: null,
            createdAt: null,
            updatedAt: null,
            namespace: 'Modules\\'.str_replace(' ', '', ucwords(str_replace('-', ' ', $name))),
            provider: 'ModuleServiceProvider',
            path: $this->testModulesPath.'/'.$name,
        );
    }

    private function removeDirectory(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        if (! is_dir($path)) {
            unlink($path);

            return;
        }

        $items = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($items as $item) {
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
