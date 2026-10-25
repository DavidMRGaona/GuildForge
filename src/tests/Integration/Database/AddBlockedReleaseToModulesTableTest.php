<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

final class AddBlockedReleaseToModulesTableTest extends TestCase
{
    use RefreshDatabase;
    use RunsMigrationFiles;

    private const string MIGRATION = 'migrations/2026_10_05_000001_add_blocked_release_to_modules_table.php';

    public function test_it_adds_the_blocked_release_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('modules', ['latest_blocked_version', 'latest_blocked_reason']));
    }

    public function test_down_removes_them_and_up_restores_them(): void
    {
        $this->runMigrationFile(database_path(self::MIGRATION), 'down');
        $this->assertFalse(Schema::hasColumn('modules', 'latest_blocked_version'));
        $this->assertFalse(Schema::hasColumn('modules', 'latest_blocked_reason'));

        $this->runMigrationFile(database_path(self::MIGRATION));
        $this->assertTrue(Schema::hasColumns('modules', ['latest_blocked_version', 'latest_blocked_reason']));
    }
}
