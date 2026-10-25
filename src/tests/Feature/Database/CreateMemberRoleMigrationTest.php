<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

/**
 * "member", the base role of every account, must exist right after migrating: module
 * migrations grant it permissions and run before RolesSeeder on a new install.
 */
final class CreateMemberRoleMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    public function test_the_member_role_exists_after_migrating(): void
    {
        $member = RoleModel::query()->where('name', 'member')->sole();

        $this->assertSame('Member', $member->display_name);
    }

    public function test_it_creates_the_member_role_when_missing(): void
    {
        RoleModel::query()->where('name', 'member')->delete();

        $this->runMigrationFile($this->migrationPath());

        $member = RoleModel::query()->where('name', 'member')->sole();
        // The same values RolesSeeder::createRole() uses for member
        $this->assertSame('Member', $member->display_name);
        $this->assertSame('Basic member access', $member->description);
        $this->assertFalse($member->is_protected);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $member->id);
        $this->assertNotNull($member->created_at);
        $this->assertNotNull($member->updated_at);
    }

    public function test_it_leaves_an_existing_member_role_untouched(): void
    {
        RoleModel::query()->where('name', 'member')->delete();
        $existing = RoleModel::query()->create([
            'name' => 'member',
            'display_name' => 'Usuario registrado',
            'description' => 'Renombrado a mano',
            'is_protected' => false,
        ]);

        $this->runMigrationFile($this->migrationPath());
        $this->runMigrationFile($this->migrationPath());

        $member = RoleModel::query()->where('name', 'member')->sole();
        $this->assertSame($existing->id, $member->id);
        $this->assertSame('Usuario registrado', $member->display_name);
        $this->assertSame('Renombrado a mano', $member->description);
    }

    public function test_roles_seeder_does_not_duplicate_it(): void
    {
        RoleModel::query()->where('name', 'member')->delete();
        $this->runMigrationFile($this->migrationPath());
        $id = RoleModel::query()->where('name', 'member')->value('id');

        $this->seed(RolesSeeder::class);

        $this->assertSame(1, RoleModel::query()->where('name', 'member')->count());
        $this->assertSame($id, RoleModel::query()->where('name', 'member')->value('id'));
    }

    public function test_down_keeps_the_role(): void
    {
        $this->runMigrationFile($this->migrationPath(), 'down');

        $this->assertTrue(RoleModel::query()->where('name', 'member')->exists());
    }

    private function migrationPath(): string
    {
        return database_path('migrations/2026_10_14_100002_create_member_role.php');
    }
}
