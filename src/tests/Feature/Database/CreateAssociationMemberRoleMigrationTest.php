<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

/**
 * Every site gets the "socio" role, the default association member role, unless it already
 * created one by hand.
 */
final class CreateAssociationMemberRoleMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    public function test_it_creates_the_socio_role_when_missing(): void
    {
        RoleModel::query()->where('name', 'socio')->delete();

        $this->runMigrationFile($this->migrationPath());

        $socio = RoleModel::query()->where('name', 'socio')->sole();
        $this->assertSame('Socio', $socio->display_name);
        $this->assertNotEmpty($socio->description);
        $this->assertFalse($socio->is_protected);
        // Same kind of id as RoleModel's HasUuids: a UUIDv7
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $socio->id);
        $this->assertNotNull($socio->created_at);
        $this->assertNotNull($socio->updated_at);
    }

    public function test_it_leaves_an_existing_socio_role_untouched(): void
    {
        RoleModel::query()->where('name', 'socio')->delete();
        $existing = RoleModel::query()->create([
            'name' => 'socio',
            'display_name' => 'Socio de la asociación',
            'description' => 'Creado a mano',
            'is_protected' => false,
        ]);

        $this->runMigrationFile($this->migrationPath());
        $this->runMigrationFile($this->migrationPath());

        $socio = RoleModel::query()->where('name', 'socio')->sole();
        $this->assertSame($existing->id, $socio->id);
        $this->assertSame('Socio de la asociación', $socio->display_name);
        $this->assertSame('Creado a mano', $socio->description);
    }

    public function test_it_can_run_twice(): void
    {
        RoleModel::query()->where('name', 'socio')->delete();

        $this->runMigrationFile($this->migrationPath());
        $this->runMigrationFile($this->migrationPath());

        $this->assertSame(1, RoleModel::query()->where('name', 'socio')->count());
    }

    public function test_down_keeps_the_role(): void
    {
        $this->runMigrationFile($this->migrationPath());

        $this->runMigrationFile($this->migrationPath(), 'down');

        $this->assertTrue(RoleModel::query()->where('name', 'socio')->exists());
    }

    private function migrationPath(): string
    {
        return database_path('migrations/2026_10_14_100001_create_association_member_role.php');
    }
}
