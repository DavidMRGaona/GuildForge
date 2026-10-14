<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Roles and permissions are cached per user for an hour: a change made in the panel must
 * apply at once, or a new member is refused and a revoked one keeps access meanwhile.
 */
final class RoleChangesRefreshAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthorizationServiceInterface $authorization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorization = app(AuthorizationServiceInterface::class);
        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_a_role_granted_from_the_user_form_applies_at_once(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $this->assertFalse($this->authorization->hasRole($user, $role->name));
        $this->assertFalse($this->authorization->can($user, 'events.create'));

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['roles' => [$role->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->authorization->hasRole($user, $role->name));
        $this->assertTrue($this->authorization->can($user, 'events.create'));
    }

    public function test_a_role_revoked_from_the_user_form_applies_at_once(): void
    {
        $user = UserModel::factory()->admin()->create();
        $this->assertTrue($this->authorization->can($user, 'users.delete'));

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['roles' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($this->authorization->hasRole($user, 'admin'));
        $this->assertFalse($this->authorization->can($user, 'users.delete'));
    }

    public function test_a_permission_granted_to_a_role_applies_at_once_to_its_users(): void
    {
        $role = $this->roleWith('events.create');
        $permission = PermissionModel::query()->create(['key' => 'events.delete', 'label' => 'events.delete', 'resource' => 'events', 'action' => 'delete']);
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->assertFalse($this->authorization->can($user, 'events.delete'));

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['permissions' => [...$role->permissions()->pluck('permissions.id')->all(), $permission->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->authorization->can($user, 'events.delete'));
    }

    public function test_refresh_user_forgets_the_cached_roles_and_permissions(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $this->assertFalse($this->authorization->can($user, 'events.create'));
        $user->roles()->attach($role->id);

        $this->authorization->refreshUser($user);

        $this->assertTrue($this->authorization->hasRole($user, $role->name));
        $this->assertTrue($this->authorization->can($user, 'events.create'));
    }

    private function roleWith(string $permissionKey): RoleModel
    {
        [$resource, $action] = explode('.', $permissionKey, 2);
        $role = RoleModel::query()->create(['name' => 'role_'.uniqid(), 'display_name' => 'Tester']);
        $permission = PermissionModel::query()->firstOrCreate(['key' => $permissionKey], ['label' => $permissionKey, 'resource' => $resource, 'action' => $action]);
        $role->permissions()->attach($permission->id);

        return $role;
    }
}
