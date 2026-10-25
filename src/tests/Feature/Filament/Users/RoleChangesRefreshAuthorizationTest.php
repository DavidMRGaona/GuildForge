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
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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
        $this->app->forgetScopedInstances();

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_a_garbage_version_key_falls_back_to_the_database(): void
    {
        $user = $this->userRevokedAfterAnEarlierCachedGrant();

        Cache::forever('authorization_cache_version', 'garbage');
        $this->app->forgetScopedInstances();

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_a_cached_lookup_of_an_unexpected_shape_is_read_again_from_the_database(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $version = str_repeat('a', 32);
        $userVersion = str_repeat('b', 32);
        Cache::forever('authorization_cache_version', $version);
        Cache::forever("authorization_cache_version_user_{$user->id}", $userVersion);
        Cache::put("user_permissions_{$version}_{$userVersion}_{$user->id}", 'events.delete', 3600);
        Cache::put("user_roles_{$version}_{$userVersion}_{$user->id}", ['admin', 42], 3600);
        $this->app->forgetScopedInstances();

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

    public function test_a_member_check_reads_the_version_token_once_per_request(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->app->forgetScopedInstances();

        // Both version tokens, cached roles (admin check) and cached permissions
        $this->assertSame(['version' => 1, 'user_version' => 1, 'total' => 4], $this->cacheReadsDuring(fn () => $this->authorization->can($user, 'events.create')));
        $this->assertSame(['version' => 0, 'user_version' => 0, 'total' => 2], $this->cacheReadsDuring(fn () => $this->authorization->can($user, 'events.delete')));
        $this->assertSame(['version' => 0, 'user_version' => 0, 'total' => 2], $this->cacheReadsDuring(fn () => $this->authorization->canAny($user, ['events.delete', 'events.create'])));

        $this->app->forgetScopedInstances();

        $this->assertSame(['version' => 1, 'user_version' => 1, 'total' => 4], $this->cacheReadsDuring(fn () => $this->authorization->canAll($user, ['events.create'])));
    }

    public function test_an_admin_check_reads_the_version_token_once_per_request(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->app->forgetScopedInstances();

        // Both version tokens and cached roles: the admin answer needs no permissions
        $this->assertSame(['version' => 1, 'user_version' => 1, 'total' => 3], $this->cacheReadsDuring(fn () => $this->authorization->can($admin, 'events.create')));
        $this->assertSame(['version' => 0, 'user_version' => 0, 'total' => 1], $this->cacheReadsDuring(fn () => $this->authorization->can($admin, 'users.delete')));
    }

    public function test_the_request_sees_its_own_invalidation(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->assertTrue($this->authorization->can($user, 'events.create'));

        $role->permissions()->detach();
        $this->authorization->invalidateCache();

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_a_failed_token_replacement_forgets_the_old_token_and_is_reported(): void
    {
        // Readers find no token and start a new one: the entries of the old token are never served
        $store = Mockery::mock(Repository::class);
        $store->shouldReceive('forever')->andThrow(new RuntimeException('cache down'));
        $store->shouldReceive('forget')->once()->with('authorization_cache_version')->andReturnTrue();
        Cache::swap($store);
        $log = Log::spy();

        try {
            $this->authorization->invalidateCache();
            $this->fail('The failed invalidation must reach the caller');
        } catch (RuntimeException $e) {
            $this->assertSame('cache down', $e->getMessage());
        }

        $log->shouldHaveReceived('error')->once();
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

    public function test_a_role_change_of_one_user_keeps_the_cached_lookups_of_the_others(): void
    {
        $role = $this->roleWith('events.create');
        $changed = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->assertFalse($this->authorization->can($changed, 'events.create'));
        $this->assertFalse($this->authorization->can($other, 'events.create'));

        $this->authorization->assignRole($changed, $role->name);

        $this->assertTrue($this->authorization->can($changed, 'events.create'));
        $this->assertSame(0, $this->databaseQueriesDuring(fn () => $this->authorization->can($other, 'events.create')));
    }

    public function test_a_role_change_from_the_user_form_keeps_the_cached_lookups_of_the_others(): void
    {
        $role = $this->roleWith('events.create');
        $changed = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->assertFalse($this->authorization->can($other, 'events.create'));

        Livewire::test(EditUser::class, ['record' => $changed->getRouteKey()])
            ->fillForm(['roles' => [$role->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->authorization->can($changed, 'events.create'));
        $this->assertSame(0, $this->databaseQueriesDuring(fn () => $this->authorization->can($other, 'events.create')));
    }

    public function test_removed_and_synced_roles_apply_at_once(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $this->authorization->assignRole($user, $role->name);
        $this->assertTrue($this->authorization->can($user, 'events.create'));

        $this->authorization->removeRole($user, $role->name);
        $this->assertFalse($this->authorization->can($user, 'events.create'));

        $this->authorization->syncRoles($user, [$role->name]);
        $this->assertTrue($this->authorization->can($user, 'events.create'));
    }

    public function test_a_user_invalidation_waits_for_the_commit(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->authorization->invalidateCacheFor($user);
        $this->assertTrue($this->authorization->can($user, 'events.create'));

        DB::transaction(function () use ($role, $user): void {
            $user->roles()->detach($role->id);
            $this->authorization->invalidateCacheFor($user);

            $this->assertTrue($this->authorization->can($user, 'events.create'), 'invalidated before the commit');
        });

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_a_lost_user_version_key_falls_back_to_the_database(): void
    {
        $user = $this->userRevokedAfterAnEarlierCachedUserGrant();

        Cache::forget("authorization_cache_version_user_{$user->id}");
        $this->app->forgetScopedInstances();

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_a_garbage_user_version_key_falls_back_to_the_database(): void
    {
        $user = $this->userRevokedAfterAnEarlierCachedUserGrant();

        Cache::forever("authorization_cache_version_user_{$user->id}", 'garbage');
        $this->app->forgetScopedInstances();

        $this->assertFalse($this->authorization->can($user, 'events.create'));
    }

    public function test_the_request_sees_its_own_user_invalidation(): void
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $this->assertFalse($this->authorization->can($user, 'events.create'));

        $user->roles()->attach($role->id);
        $this->authorization->invalidateCacheFor($user);

        $this->assertTrue($this->authorization->can($user, 'events.create'));
    }

    public function test_a_failed_user_token_replacement_forgets_the_old_token_and_is_reported(): void
    {
        $user = UserModel::factory()->create();
        $store = Mockery::mock(Repository::class);
        $store->shouldReceive('forever')->andThrow(new RuntimeException('cache down'));
        $store->shouldReceive('forget')->once()->with("authorization_cache_version_user_{$user->id}")->andReturnTrue();
        Cache::swap($store);
        $log = Log::spy();

        try {
            $this->authorization->invalidateCacheFor($user);
            $this->fail('The failed invalidation must reach the caller');
        } catch (RuntimeException $e) {
            $this->assertSame('cache down', $e->getMessage());
        }

        $log->shouldHaveReceived('error')->once();
    }

    /**
     * A user cached with events.create under a first user token, who then lost the role
     * (invalidated for that user only): the entries of that token are still in the store.
     */
    private function userRevokedAfterAnEarlierCachedUserGrant(): UserModel
    {
        $role = $this->roleWith('events.create');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->assertTrue($this->authorization->can($user, 'events.create'));
        $user->roles()->detach($role->id);
        $this->authorization->invalidateCacheFor($user);
        $this->assertFalse($this->authorization->can($user, 'events.create'));

        return $user;
    }

    private function databaseQueriesDuring(callable $check): int
    {
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $check();

        return $queries;
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

    /**
     * @return array{version: int, user_version: int, total: int}
     */
    private function cacheReadsDuring(callable $check): array
    {
        $keys = [];
        Event::listen(RetrievingKey::class, static function (RetrievingKey $event) use (&$keys): void {
            $keys[] = $event->key;
        });

        $check();
        Event::forget(RetrievingKey::class);

        return [
            'version' => count(array_filter($keys, static fn (string $key): bool => $key === 'authorization_cache_version')),
            'user_version' => count(array_filter($keys, static fn (string $key): bool => str_starts_with($key, 'authorization_cache_version_user_'))),
            'total' => count($keys),
        ];
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
