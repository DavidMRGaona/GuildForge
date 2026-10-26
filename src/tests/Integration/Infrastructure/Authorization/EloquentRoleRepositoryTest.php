<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Authorization;

use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Authorization\ValueObjects\RoleName;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EloquentRoleRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_repository_round_trips_requirement_and_limit(): void
    {
        $repository = $this->app->make(RoleRepositoryInterface::class);
        $id = RoleId::generate();

        $repository->save(new Role(
            id: $id,
            name: new RoleName('capped'),
            displayName: 'Capped',
            requiresAssociationMember: true,
            maxHolders: 3,
        ));

        $role = $repository->findById($id);

        $this->assertNotNull($role);
        $this->assertTrue($role->requiresAssociationMember());
        $this->assertSame(3, $role->maxHolders());

        $repository->save(new Role(
            id: $id,
            name: new RoleName('capped'),
            displayName: 'Capped',
        ));

        $role = $repository->findById($id);

        $this->assertNotNull($role);
        $this->assertFalse($role->requiresAssociationMember());
        $this->assertNull($role->maxHolders());
    }

    public function test_role_names_for_user_include_every_role(): void
    {
        $repository = $this->app->make(RoleRepositoryInterface::class);
        $user = UserModel::factory()->create();
        $first = RoleModel::query()->create(['name' => 'role-a', 'display_name' => 'A']);
        $second = RoleModel::query()->create(['name' => 'role-b', 'display_name' => 'B']);
        RoleModel::query()->create(['name' => 'role-c', 'display_name' => 'C']);
        $user->roles()->attach([$first->id, $second->id]);

        $names = $repository->getRoleNamesForUser($user->id);
        sort($names);

        $this->assertSame(['role-a', 'role-b'], $names);
        $this->assertSame([], $repository->getRoleNamesForUser((string) Str::uuid()));
    }
}
