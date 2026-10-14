<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteAction;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
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

    public function test_a_permission_revoked_from_a_role_applies_at_once_to_its_users(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->authorization->invalidateCache();
        $this->assertTrue($this->authorization->can($user, 'events.create'));

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['permissions' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_deleting_a_role_revokes_it_at_once_from_its_users(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->authorization->invalidateCache();
        $this->assertTrue($this->authorization->can($user, 'events.create'));

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertFalse($this->authorization->hasRole($user, $role->name));
        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_deleting_a_role_from_the_table_revokes_it_at_once_from_its_users(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->authorization->invalidateCache();
        $this->assertTrue($this->authorization->can($user, 'events.create'));

        Livewire::test(ListRoles::class)
            ->callTableAction(DeleteAction::class, $role);

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_permission_changes_through_the_role_service_apply_at_once(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->authorization->invalidateCache();
        $this->assertTrue($this->authorization->can($user, 'events.create'));

        app(RoleServiceInterface::class)->syncPermissions($role->id, []);

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_the_cache_is_invalidated_once_the_change_is_committed_not_before(): void
    {
        // A request between an early invalidation and the commit would cache the old
        // permissions again for an hour
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->authorization->invalidateCache();
        $this->assertTrue($this->authorization->can($user, 'events.create'));

        DB::transaction(function () use ($role, $user): void {
            $role->permissions()->detach();
            $this->authorization->invalidateCache();

            $this->assertTrue($this->authorization->can($user, 'events.create'), 'invalidated before the commit');
        });

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_invalidation_works_with_the_database_cache_store(): void
    {
        // The default store: unlike the array store, it does not increment a missing key
        Cache::setDefaultDriver('database');
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $this->assertFalse($this->authorization->can($user, 'events.create'));
        $user->roles()->attach($role->id);

        $this->authorization->invalidateCache();

        $this->assertTrue($this->authorization->can($user, 'events.create'));
    }

    public function test_a_lost_version_key_falls_back_to_the_database(): void
    {
        // Evicted alone: entries cached under an earlier version must not come back
        $user = $this->userRevokedAfterAnEarlierCachedGrant();

        Cache::forget('authorization_cache_version');

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_a_garbage_version_key_falls_back_to_the_database(): void
    {
        $user = $this->userRevokedAfterAnEarlierCachedGrant();

        Cache::forever('authorization_cache_version', 'garbage');

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_a_cached_lookup_of_an_unexpected_shape_is_read_again_from_the_database(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $version = str_repeat('a', 32);
        Cache::forever('authorization_cache_version', $version);
        Cache::put("user_permissions_{$version}_{$user->id}", 'events.delete', 3600);
        Cache::put("user_roles_{$version}_{$user->id}", ['admin', 42], 3600);

        $this->assertTrue($this->authorization->can($user, 'events.create'));
        $this->assertFalse($this->authorization->can($user, 'events.delete'));
        $this->assertFalse($this->authorization->hasRole($user, 'admin'));
    }

    public function test_an_unavailable_cache_store_falls_back_to_the_database(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $broken = Mockery::mock(Repository::class);
        $broken->shouldReceive('get', 'put', 'forever', 'add', 'remember')->andThrow(new RuntimeException('cache down'));
        Cache::swap($broken);

        $this->assertTrue($this->authorization->can($user, 'events.create'));
        $this->assertFalse($this->authorization->can($user, 'events.delete'));
        $this->assertFalse($this->authorization->hasRole($user, 'admin'));
    }

    public function test_invalidate_cache_forgets_the_cached_roles_and_permissions(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $this->assertFalse($this->authorization->can($user, 'events.create'));
        $user->roles()->attach($role->id);

        $this->authorization->invalidateCache();

        $this->assertTrue($this->authorization->hasRole($user, $role->name));
        $this->assertTrue($this->authorization->can($user, 'events.create'));
    }

    /**
     * A user cached with events.create under the first version, whose role then lost it
     * (invalidated): the entries of that first version are still in the store.
     */
    private function userRevokedAfterAnEarlierCachedGrant(): UserModel
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->assertTrue($this->authorization->can($user, 'events.create'));
        $role->permissions()->detach();
        $this->authorization->invalidateCache();
        $this->assertFalse($this->authorization->can($user, 'events.create'));

        return $user;
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
