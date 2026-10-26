<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Authorization;

use App\Infrastructure\Authorization\Services\PermissionHolderFinder;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class PermissionHolderFinderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_admins_and_holders_of_every_permission(): void
    {
        $admin = UserModel::factory()->admin()->create(['name' => 'Admin']);
        $both = $this->userWithRole('both', ['events.update', 'events.delete'], 'Both');
        $this->userWithRole('only-one', ['events.update'], 'One');
        UserModel::factory()->create(['name' => 'Nobody']);

        $found = app(PermissionHolderFinder::class)->usersWhoCan('events.update', 'events.delete');

        $this->assertSame([$admin->id, $both->id], $found->pluck('id')->all());
    }

    public function test_holders_through_several_roles_are_returned_once(): void
    {
        $user = $this->userWithRole('first', ['events.update'], 'Multi');
        $second = $this->role('second', ['events.update']);
        $user->roles()->attach($second->id);

        $found = app(PermissionHolderFinder::class)->usersWhoCan('events.update');

        $this->assertSame([$user->id], $found->pluck('id')->all());
    }

    public function test_skips_inactive_role_holders(): void
    {
        $role = $this->role('vocal', ['events.update'], true);
        $inactive = UserModel::factory()->create();
        $inactive->roles()->attach($role->id);

        $socio = RoleModel::query()->where('name', 'socio')->firstOrFail();
        $active = UserModel::factory()->create();
        $active->roles()->attach([$role->id, $socio->id]);

        $found = app(PermissionHolderFinder::class)->usersWhoCan('events.update');

        $this->assertSame([$active->id], $found->pluck('id')->all());
    }

    public function test_skips_deactivated_and_anonymized_accounts(): void
    {
        $deleted = $this->userWithRole('role-a', ['events.update'], 'Deleted');
        $deleted->delete();
        $anonymized = $this->userWithRole('role-b', ['events.update'], 'Anonymized');
        $anonymized->forceFill(['anonymized_at' => now()])->save();
        $kept = UserModel::factory()->admin()->create();

        $found = app(PermissionHolderFinder::class)->usersWhoCan('events.update');

        $this->assertSame([$kept->id], $found->pluck('id')->all());
    }

    public function test_refuses_an_empty_permission_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PermissionHolderFinder::class)->usersWhoCan();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithRole(string $roleName, array $permissions, string $userName): UserModel
    {
        $user = UserModel::factory()->create(['name' => $userName]);
        $user->roles()->attach($this->role($roleName, $permissions)->id);

        return $user;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function role(string $name, array $permissions, bool $restricted = false): RoleModel
    {
        $role = RoleModel::query()->create([
            'name' => $name,
            'display_name' => ucfirst($name),
            'requires_association_member' => $restricted,
        ]);

        foreach ($permissions as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => $resource, 'action' => $action],
            );
            $role->permissions()->attach($permission->id);
        }

        return $role;
    }
}
