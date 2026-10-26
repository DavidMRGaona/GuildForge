<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * A role that requires association membership grants nothing (no permissions, and hasRole is
 * false) while its holder does not hold the association member role, and works again as soon
 * as they do. The user's other roles are never affected.
 */
final class InactiveRoleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string PERMISSION = 'memberships:members.view_any';

    private AuthorizationServiceInterface $authorization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorization = app(AuthorizationServiceInterface::class);

        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $this->roleWithPermissions('treasury', ['admin.access', self::PERMISSION], requiresMembership: true);
    }

    public function test_role_requiring_membership_grants_nothing_without_the_association_role(): void
    {
        $user = $this->userWithRoles(['treasury']);

        $this->assertFalse($this->authorization->can($user, self::PERMISSION));
        $this->assertFalse($this->authorization->can($user, 'admin.access'));
        $this->assertFalse($this->authorization->canAny($user, [self::PERMISSION, 'admin.access']));
        $this->assertFalse($this->authorization->hasRole($user, 'treasury'));
        $this->assertSame([], $this->authorization->getPermissions($user));
        $this->assertNotContains('treasury', $this->authorization->getRoles($user));
    }

    public function test_role_requiring_membership_works_while_the_user_is_an_association_member(): void
    {
        $user = $this->userWithRoles(['treasury', 'socio']);

        $this->assertTrue($this->authorization->can($user, self::PERMISSION));
        $this->assertTrue($this->authorization->canAll($user, [self::PERMISSION, 'admin.access']));
        $this->assertTrue($this->authorization->hasRole($user, 'treasury'));
        $this->assertTrue($this->authorization->hasRole($user, 'socio'));
        $this->assertTrue(app(AssociationMembershipServiceInterface::class)->isAssociationMember($user));
    }

    public function test_other_roles_keep_working_when_one_is_inactive(): void
    {
        $this->roleWithPermissions('editor', ['articles.create']);
        $user = $this->userWithRoles(['treasury', 'editor']);

        $this->assertTrue($this->authorization->can($user, 'articles.create'));
        $this->assertTrue($this->authorization->hasRole($user, 'editor'));
        $this->assertFalse($this->authorization->can($user, self::PERMISSION));
        $this->assertSame(['articles.create'], $this->authorization->getPermissions($user));
    }

    public function test_losing_the_association_role_deactivates_at_once(): void
    {
        $user = $this->userWithRoles(['treasury']);
        $this->assertFalse($this->authorization->can($user, self::PERMISSION));

        $this->authorization->assignRole($user, 'socio');
        $this->assertTrue($this->authorization->can($user, self::PERMISSION));
        $this->assertTrue($this->authorization->hasRole($user, 'treasury'));

        $this->authorization->removeRole($user, 'socio');
        $this->assertFalse($this->authorization->can($user, self::PERMISSION));
        $this->assertFalse($this->authorization->hasRole($user, 'treasury'));
    }

    public function test_nothing_requiring_membership_is_active_when_the_setting_holds_a_forbidden_role(): void
    {
        $this->roleWithPermissions('editor', ['articles.create']);
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'editor');
        $user = $this->userWithRoles(['treasury', 'editor']);

        $this->assertNull(app(AssociationMembershipServiceInterface::class)->roleName());
        $this->assertFalse($this->authorization->can($user, self::PERMISSION));
        $this->assertFalse($this->authorization->hasRole($user, 'treasury'));
        $this->assertTrue($this->authorization->can($user, 'articles.create'));
        $this->assertTrue($this->authorization->hasRole($user, 'editor'));
    }

    public function test_roles_requiring_membership_stay_inactive_when_the_setting_cannot_be_read(): void
    {
        $this->roleWithPermissions('editor', ['articles.create']);
        $user = $this->userWithRoles(['treasury', 'socio', 'editor']);
        $broken = Mockery::mock(Repository::class);
        $broken->shouldReceive('get', 'put', 'forever', 'add', 'remember', 'rememberForever')->andThrow(new RuntimeException('cache down'));
        Cache::swap($broken);

        $this->assertFalse($this->authorization->can($user, self::PERMISSION));
        $this->assertFalse($this->authorization->hasRole($user, 'treasury'));
        $this->assertTrue($this->authorization->hasRole($user, 'socio'));
        $this->assertTrue($this->authorization->can($user, 'articles.create'));
    }

    public function test_changing_the_association_role_setting_invalidates_the_cache(): void
    {
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'socio');
        $user = $this->userWithRoles(['treasury', 'junta']);

        // Cached while the association role is still "socio"
        $this->assertFalse($this->authorization->can($user, self::PERMISSION));

        $this->actingAs(UserModel::factory()->admin()->create());
        Livewire::test(SiteSettings::class)
            ->fillForm([AssociationMembershipServiceInterface::SETTING_KEY => 'junta'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->authorization->can($user, self::PERMISSION));
        $this->assertTrue($this->authorization->hasRole($user, 'treasury'));
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    private function roleWithPermissions(string $name, array $permissionKeys, bool $requiresMembership = false): RoleModel
    {
        $role = RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
        $role->requires_association_member = $requiresMembership;
        $role->save();

        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => $resource, 'action' => $action],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $role;
    }

    /**
     * @param  array<string>  $roleNames
     */
    private function userWithRoles(array $roleNames): UserModel
    {
        $user = UserModel::factory()->create();
        $user->roles()->attach(RoleModel::query()->whereIn('name', $roleNames)->pluck('id')->all());

        return $user;
    }
}
