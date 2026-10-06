<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AddBlockedReleaseToModulesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_the_blocked_release_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('modules', ['latest_blocked_version', 'latest_blocked_reason']));
    }

    public function test_down_removes_them_and_up_restores_them(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_10_05_000001_add_blocked_release_to_modules_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('modules', 'latest_blocked_version'));
        $this->assertFalse(Schema::hasColumn('modules', 'latest_blocked_reason'));

        $migration->up();
        $this->assertTrue(Schema::hasColumns('modules', ['latest_blocked_version', 'latest_blocked_reason']));
    }
}
