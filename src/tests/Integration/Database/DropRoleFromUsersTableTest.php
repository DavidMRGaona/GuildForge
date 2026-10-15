<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Roles live in the user_role pivot; the legacy users.role column is gone.
 */
final class DropRoleFromUsersTableTest extends TestCase
{
    use RefreshDatabase;

    private const string MIGRATION = 'migrations/2026_10_15_100001_drop_role_from_users_table.php';

    public function test_the_users_table_has_no_role_column(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'role'));
    }

    public function test_dropping_the_column_keeps_the_users(): void
    {
        $migration = $this->migration();
        $migration->down();
        $user = UserModel::factory()->create(['email' => 'kept@example.com']);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'kept@example.com']);
    }

    public function test_down_restores_the_column_as_not_null_with_member_as_default(): void
    {
        $existing = UserModel::factory()->create();

        $this->migration()->down();

        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'role');
        $this->assertIsArray($column);
        $this->assertFalse($column['nullable']);
        $this->assertSame('member', DB::table('users')->where('id', $existing->id)->value('role'));

        $id = $this->insertUser();
        $this->assertSame('member', DB::table('users')->where('id', $id)->value('role'));

        $this->expectException(QueryException::class);
        $this->insertUser(['role' => null]);
    }

    public function test_up_after_down_drops_it_again(): void
    {
        $migration = $this->migration();

        $migration->down();
        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'role'));
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path(self::MIGRATION);

        return $migration;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertUser(array $attributes = []): string
    {
        $id = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Raw user',
            'email' => $id.'@example.com',
            'password' => 'secret',
            ...$attributes,
        ]);

        return $id;
    }
}
