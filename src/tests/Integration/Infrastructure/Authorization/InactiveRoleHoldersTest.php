<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Authorization;

use App\Infrastructure\Authorization\Services\InactiveRoleHolders;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class InactiveRoleHoldersTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_counts_holders_of_restricted_roles_who_lack_the_association_role(): void
    {
        $restricted = $this->restrictedRole('vocal');
        $socio = RoleModel::query()->where('name', 'socio')->firstOrFail();

        $inactive = UserModel::factory()->create();
        $inactive->roles()->attach($restricted->id);

        $active = UserModel::factory()->create();
        $active->roles()->attach([$restricted->id, $socio->id]);

        $plain = UserModel::factory()->create();
        $plain->roles()->attach($socio->id);

        $holders = app(InactiveRoleHolders::class);

        $this->assertSame(1, $holders->count('socio'));
        $this->assertSame([$inactive->id], $holders->query('socio')->pluck('users.id')->all());
    }

    public function test_a_null_association_role_counts_every_holder(): void
    {
        $restricted = $this->restrictedRole('vocal');
        $socio = RoleModel::query()->where('name', 'socio')->firstOrFail();

        UserModel::factory()->create()->roles()->attach($restricted->id);
        UserModel::factory()->create()->roles()->attach([$restricted->id, $socio->id]);

        $this->assertSame(2, app(InactiveRoleHolders::class)->count(null));
    }

    public function test_the_result_depends_on_the_role_given(): void
    {
        $restricted = $this->restrictedRole('vocal');
        $other = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta']);
        $socio = RoleModel::query()->where('name', 'socio')->firstOrFail();

        $user = UserModel::factory()->create();
        $user->roles()->attach([$restricted->id, $socio->id]);

        $holders = app(InactiveRoleHolders::class);

        $this->assertSame(0, $holders->count('socio'));
        $this->assertSame(1, $holders->count($other->name));
    }

    public function test_deleted_and_anonymized_accounts_are_left_out_and_each_user_counts_once(): void
    {
        $restricted = $this->restrictedRole('vocal');
        $second = $this->restrictedRole('tesorero');

        $deleted = UserModel::factory()->create();
        $deleted->roles()->attach($restricted->id);
        $deleted->delete();

        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);
        $anonymized->roles()->attach($restricted->id);

        $both = UserModel::factory()->create();
        $both->roles()->attach([$restricted->id, $second->id]);

        $this->assertSame(1, app(InactiveRoleHolders::class)->count('socio'));
    }

    public function test_unrestricted_roles_do_not_count(): void
    {
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta']);
        UserModel::factory()->create()->roles()->attach($junta->id);

        $this->assertSame(0, app(InactiveRoleHolders::class)->count('socio'));
    }

    private function restrictedRole(string $name): RoleModel
    {
        return RoleModel::query()->create([
            'name' => $name,
            'display_name' => ucfirst($name),
            'requires_association_member' => true,
        ]);
    }
}
