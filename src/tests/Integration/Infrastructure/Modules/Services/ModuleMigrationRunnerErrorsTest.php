<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleMigrationAnalyzerInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\CoreTableRegistry;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModuleMigrationRunner;
use App\Infrastructure\Modules\Services\ModuleSchemaGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Runs real module migrations: a failure must reach the caller (the ZIP update restores its
 * backup on it), never be swallowed and reported as migrations run.
 */
final class ModuleMigrationRunnerErrorsTest extends TestCase
{
    use RefreshDatabase;

    private const string MODULE = 'mmr-test';

    private string $modulesPath;

    private ModuleMigrationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/migration-runner-'.bin2hex(random_bytes(8)));
        File::makeDirectory($this->modulesPath.'/'.self::MODULE.'/database/migrations', 0755, true);

        $this->runner = new ModuleMigrationRunner(
            $this->modulesPath,
            $this->createStub(ModuleMigrationAnalyzerInterface::class),
            new ModuleSchemaGuard(new CoreTableRegistry),
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_it_runs_the_module_migrations(): void
    {
        $this->writeMigration(up: "Schema::create('mmr_test_widgets', fn (\$table) => \$table->id());");

        $this->assertSame(1, $this->runner->run($this->module()));
        $this->assertTrue(Schema::hasTable('mmr_test_widgets'));
    }

    public function test_a_failing_migration_reaches_the_caller(): void
    {
        $this->writeMigration(up: "throw new RuntimeException('Migration failed on purpose');");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration failed on purpose');

        $this->runner->run($this->module());
    }

    public function test_a_failing_rollback_reaches_the_caller(): void
    {
        $this->writeMigration(
            up: "Schema::create('mmr_test_widgets', fn (\$table) => \$table->id());",
            down: "throw new RuntimeException('Rollback failed on purpose');",
        );
        $this->runner->run($this->module());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rollback failed on purpose');

        $this->runner->rollback($this->module());
    }

    public function test_rolling_back_everything_undoes_the_migrations_of_the_module_whenever_they_ran(): void
    {
        // Three migrations of the module, then more from another module and the module's own, later on
        foreach ([1, 2, 3] as $n) {
            $this->writeMigration(
                up: "Schema::create('mmr_test_t{$n}', fn (\$table) => \$table->id());",
                down: "Schema::dropIfExists('mmr_test_t{$n}');",
                name: "2026_01_01_00000{$n}_mmr_test_{$n}",
            );
        }
        $this->runner->run($this->module());

        $other = 'mmr-other';
        File::makeDirectory($this->modulesPath.'/'.$other.'/database/migrations', 0755, true);
        foreach ([1, 2, 3, 4, 5] as $n) {
            $this->writeMigration(
                up: "Schema::create('mmr_other_t{$n}', fn (\$table) => \$table->id());",
                down: "Schema::dropIfExists('mmr_other_t{$n}');",
                name: "2026_02_01_00000{$n}_mmr_other_{$n}",
                module: $other,
            );
        }
        $this->runner->run($this->moduleNamed($other));

        $this->runner->rollbackAll($this->module());

        foreach ([1, 2, 3] as $n) {
            $this->assertFalse(Schema::hasTable("mmr_test_t{$n}"), "mmr_test_t{$n} is still there");
            $this->assertDatabaseMissing('migrations', ['migration' => "2026_01_01_00000{$n}_mmr_test_{$n}"]);
        }
        foreach ([1, 2, 3, 4, 5] as $n) {
            $this->assertTrue(Schema::hasTable("mmr_other_t{$n}"), "mmr_other_t{$n} was removed");
            $this->assertDatabaseHas('migrations', ['migration' => "2026_02_01_00000{$n}_mmr_other_{$n}"]);
        }
    }

    public function test_a_failing_rollback_of_everything_reaches_the_caller(): void
    {
        $this->writeMigration(
            up: "Schema::create('mmr_test_widgets', fn (\$table) => \$table->id());",
            down: "throw new RuntimeException('Rollback failed on purpose');",
        );
        $this->runner->run($this->module());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rollback failed on purpose');

        $this->runner->rollbackAll($this->module());
    }

    public function test_falling_back_to_the_default_path_logs_a_warning(): void
    {
        $log = Log::spy();
        $stalePath = $this->modulesPath.'/moved-away';

        $this->assertSame(0, $this->runner->run($this->module(path: $stalePath)));

        $log->shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Stored path of module '.self::MODULE.' does not exist, using the modules directory'
                && $context === ['stored_path' => $stalePath, 'path' => $this->modulesPath.'/'.self::MODULE]);
    }

    private function writeMigration(string $up, string $down = '', string $name = '2026_01_01_000000_mmr_test', string $module = self::MODULE): void
    {
        File::put(
            $this->modulesPath.'/'.$module.'/database/migrations/'.$name.'.php',
            <<<PHP
            <?php

            use Illuminate\\Database\\Migrations\\Migration;
            use Illuminate\\Support\\Facades\\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    {$up}
                }

                public function down(): void
                {
                    {$down}
                }
            };
            PHP,
        );
    }

    private function module(?string $path = null): Module
    {
        return $this->moduleNamed(self::MODULE, $path);
    }

    private function moduleNamed(string $name, ?string $path = null): Module
    {
        return new Module(
            id: ModuleId::generate(),
            name: new ModuleName($name),
            displayName: 'Migration runner test',
            description: 'Module whose migrations the test writes',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test Author',
            requirements: new ModuleRequirements(
                phpVersion: null,
                laravelVersion: null,
                requiredModules: [],
                requiredExtensions: [],
            ),
            status: ModuleStatus::Enabled,
            path: $path ?? $this->modulesPath.'/'.$name,
        );
    }
}
