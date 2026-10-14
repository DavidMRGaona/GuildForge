<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Every site gets the "socio" role, the default association member role, unless it already
 * created one by hand.
 */
final class CreateAssociationMemberRoleMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_creates_the_socio_role_when_missing(): void
    {
        RoleModel::query()->where('name', 'socio')->delete();

        $this->migration()->up();

        $socio = RoleModel::query()->where('name', 'socio')->sole();
        $this->assertSame('Socio', $socio->display_name);
        $this->assertNotEmpty($socio->description);
        $this->assertFalse($socio->is_protected);
        $this->assertTrue(str_contains($socio->id, '-'), 'The id is a UUID');
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

        $this->migration()->up();
        $this->migration()->up();

        $socio = RoleModel::query()->where('name', 'socio')->sole();
        $this->assertSame($existing->id, $socio->id);
        $this->assertSame('Socio de la asociación', $socio->display_name);
        $this->assertSame('Creado a mano', $socio->description);
    }

    public function test_it_can_run_twice(): void
    {
        RoleModel::query()->where('name', 'socio')->delete();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, RoleModel::query()->where('name', 'socio')->count());
    }

    public function test_down_keeps_the_role(): void
    {
        $this->migration()->up();

        $this->migration()->down();

        $this->assertTrue(RoleModel::query()->where('name', 'socio')->exists());
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_14_100001_create_association_member_role.php');
    }
}
