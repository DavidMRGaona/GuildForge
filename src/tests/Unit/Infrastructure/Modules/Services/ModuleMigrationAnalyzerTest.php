<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Domain\Modules\Exceptions\ModuleMigrationViolationException;
use App\Domain\Modules\ValueObjects\CoreTableRegistry;
use App\Infrastructure\Modules\Services\ModuleMigrationAnalyzer;
use PHPUnit\Framework\TestCase;

final class ModuleMigrationAnalyzerTest extends TestCase
{
    private ModuleMigrationAnalyzer $analyzer;

    private string $migrationsPath;

    private string $seedersPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = new ModuleMigrationAnalyzer(new CoreTableRegistry);

        $basePath = sys_get_temp_dir().'/module_migration_analyzer_test_'.uniqid();
        $this->migrationsPath = $basePath.'/migrations';
        $this->seedersPath = $basePath.'/seeders';

        mkdir($this->migrationsPath, 0777, true);
        mkdir($this->seedersPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->migrationsPath);
        $this->removeDirectory($this->seedersPath);
        rmdir(dirname($this->migrationsPath));

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Migration tests
    // ---------------------------------------------------------------

    public function test_it_passes_for_module_creating_its_own_table(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_15_create_gametables_games.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }
};
PHP);

        // Act & Assert - should not throw
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);

        $this->assertTrue(true);
    }

    public function test_it_passes_for_foreign_key_referencing_core_table(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_16_create_gametables_players.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->timestamps();
        });
    }
};
PHP);

        // Act & Assert - should not throw
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);

        $this->assertTrue(true);
    }

    public function test_it_detects_schema_table_on_core_table(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_17_modify_users.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable();
        });
    }
};
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);
    }

    public function test_it_detects_schema_drop_on_core_table(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_18_drop_events.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('events');
    }
};
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);
    }

    public function test_it_detects_schema_drop_if_exists_on_core_table(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_19_drop_articles.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('articles');
    }
};
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);
    }

    public function test_it_detects_schema_rename_involving_core_table(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_20_rename_users.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('users', 'old_users');
    }
};
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);
    }

    public function test_it_detects_schema_create_with_core_table_name(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_21_create_users.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }
};
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);
    }

    public function test_it_detects_raw_sql_ddl_on_core_table(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_22_alter_users.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ADD COLUMN foo VARCHAR(255)');
    }
};
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);
    }

    public function test_it_flags_dynamic_table_name_in_schema_call(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_23_dynamic_table.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($tableName, function (Blueprint $table) {
            $table->string('extra')->nullable();
        });
    }
};
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);
    }

    public function test_it_flags_unanalyzable_db_statement(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_24_dynamic_sql.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement($query);
    }
};
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);
    }

    public function test_it_passes_for_empty_migrations_directory(): void
    {
        // Act & Assert - should not throw for empty directory
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);

        $this->assertTrue(true);
    }

    public function test_it_passes_for_nonexistent_directory(): void
    {
        // Arrange
        $nonExistentPath = sys_get_temp_dir().'/nonexistent_migrations_'.uniqid();

        // Act & Assert - should not throw for nonexistent directory
        $this->analyzer->analyzeMigrations('game-tables', $nonExistentPath);

        $this->assertTrue(true);
    }

    public function test_it_detects_cross_module_table_access(): void
    {
        // Arrange
        $this->createMigrationFile('2024_01_25_modify_bookings.php', <<<'PHP'
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings_rooms', function (Blueprint $table) {
            $table->string('game_type')->nullable();
        });
    }
};
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeMigrations('game-tables', $this->migrationsPath);
    }

    // ---------------------------------------------------------------
    // Seeder tests
    // ---------------------------------------------------------------

    public function test_seeder_passes_for_insert_on_module_table(): void
    {
        // Arrange
        $this->createSeederFile('GametablesGamesSeeder.php', <<<'PHP'
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GametablesGamesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('gametables_games')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Test Game',
        ]);
    }
}
PHP);

        // Act & Assert - should not throw
        $this->analyzer->analyzeSeeders('game-tables', $this->seedersPath);

        $this->assertTrue(true);
    }

    public function test_seeder_detects_delete_on_core_table(): void
    {
        // Arrange
        $this->createSeederFile('BadSeeder.php', <<<'PHP'
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->delete();
    }
}
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeSeeders('game-tables', $this->seedersPath);
    }

    public function test_seeder_detects_truncate_on_core_table(): void
    {
        // Arrange
        $this->createSeederFile('TruncateSeeder.php', <<<'PHP'
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TruncateSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('events')->truncate();
    }
}
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeSeeders('game-tables', $this->seedersPath);
    }

    public function test_seeder_detects_schema_call(): void
    {
        // Arrange
        $this->createSeederFile('SchemaSeeder.php', <<<'PHP'
use Illuminate\Database\Seeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SchemaSeeder extends Seeder
{
    public function run(): void
    {
        Schema::create('something', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
        });
    }
}
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeSeeders('game-tables', $this->seedersPath);
    }

    public function test_seeder_detects_raw_sql_delete_on_core_table(): void
    {
        // Arrange
        $this->createSeederFile('RawDeleteSeeder.php', <<<'PHP'
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RawDeleteSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('DELETE FROM users');
    }
}
PHP);

        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->analyzer->analyzeSeeders('game-tables', $this->seedersPath);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createMigrationFile(string $filename, string $content): void
    {
        file_put_contents($this->migrationsPath.'/'.$filename, "<?php\n".$content);
    }

    private function createSeederFile(string $filename, string $content): void
    {
        file_put_contents($this->seedersPath.'/'.$filename, "<?php\n".$content);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = glob($path.'/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $this->removeDirectory($file);
                } else {
                    unlink($file);
                }
            }
        }

        rmdir($path);
    }
}
