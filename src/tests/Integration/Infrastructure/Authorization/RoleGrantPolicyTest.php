<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Authorization;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Domain\Authorization\Exceptions\RoleGrantDeniedException;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Nobody grants more than they hold: a role can be given or taken only by an admin, or by
 * someone with users.assign_roles who holds every permission of that role.
 */
final class RoleGrantPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_grant_any_role_including_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->role('tesoreria', ['users.view_any', 'settings.manage']);

        $policy = $this->policy();

        $this->assertTrue($policy->canGrant($admin, 'admin'));
        $this->assertTrue($policy->canGrant($admin, 'tesoreria'));
        $this->assertTrue($policy->canGrant($admin, 'member'));
        $this->assertFalse($policy->canGrant($admin, 'unknown-role'));
        $this->assertNull($policy->editablePermissionKeys($admin));
        $this->assertContains('admin', $policy->grantableRoleNames($admin));
        $this->assertContains('tesoreria', $policy->grantableRoleNames($admin));
    }

    public function test_without_users_assign_roles_nothing_is_grantable(): void
    {
        $this->role('contenido', ['articles.view_any']);
        $actor = $this->userWith('secretaria', ['articles.view_any', 'users.view_any']);

        $policy = $this->policy();

        $this->assertFalse($policy->canGrant($actor, 'contenido'));
        $this->assertFalse($policy->canGrant($actor, 'member'));
        $this->assertSame([], $policy->grantableRoleNames($actor));
    }

    public function test_a_role_with_a_permission_the_actor_lacks_is_not_grantable(): void
    {
        $this->role('contenido', ['articles.view_any']);
        $this->role('tesoreria', ['articles.view_any', 'settings.manage']);
        $actor = $this->userWith('secretaria', ['users.assign_roles', 'articles.view_any']);

        $policy = $this->policy();

        $this->assertTrue($policy->canGrant($actor, 'contenido'));
        $this->assertTrue($policy->canGrant($actor, 'member'));
        $this->assertFalse($policy->canGrant($actor, 'tesoreria'));
        $this->assertFalse($policy->canGrant($actor, 'unknown-role'));

        $grantable = $policy->grantableRoleNames($actor);
        $this->assertContains('contenido', $grantable);
        $this->assertNotContains('tesoreria', $grantable);

        $editable = $policy->editablePermissionKeys($actor);
        $this->assertNotNull($editable);
        $this->assertEqualsCanonicalizing(['users.assign_roles', 'articles.view_any'], $editable);
    }

    public function test_admin_role_is_never_grantable_by_a_non_admin_even_with_every_permission(): void
    {
        $permissions = ['users.assign_roles', 'users.view_any', 'settings.manage'];
        $this->attach(RoleModel::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']), $permissions);
        $actor = $this->userWith('presidencia', $permissions);

        $policy = $this->policy();

        $this->assertFalse($policy->canGrant($actor, 'admin'));
        $this->assertNotContains('admin', $policy->grantableRoleNames($actor));
    }

    public function test_removing_needs_the_same_right_as_granting(): void
    {
        $this->role('contenido', ['articles.view_any']);
        $this->role('tesoreria', ['settings.manage']);
        $actor = $this->userWith('secretaria', ['users.assign_roles', 'articles.view_any']);
        $target = $this->userWith('tesoreria', []);
        $plain = UserModel::factory()->create();

        $policy = $this->policy();

        $this->assertTrue($policy->canChangeRoles($actor, $target, ['member', 'tesoreria'], ['member', 'tesoreria', 'contenido']));
        $this->assertFalse($policy->canChangeRoles($actor, $target, ['member', 'tesoreria'], ['member']));
        $this->assertFalse($policy->canChangeRoles($actor, $plain, ['member'], ['member', 'tesoreria']));
        $this->assertTrue($policy->canChangeRoles($actor, $target, ['member', 'tesoreria'], ['tesoreria', 'member']));

        try {
            $policy->assertCanChangeRoles($actor, $target, ['member', 'tesoreria'], ['member', 'contenido']);
            $this->fail('Removing a role the actor cannot grant must be refused.');
        } catch (RoleGrantDeniedException $e) {
            $this->assertSame('No puedes dar ni quitar estos roles: Tesorería.', $e->getMessage());
        }
    }

    public function test_granting_the_association_role_needs_the_dormant_roles(): void
    {
        $this->role('presidencia', ['users.assign_roles', 'users.view_any', 'settings.manage'], requiresAssociationMember: true);
        $target = $this->userWith('presidencia', []);
        $actor = $this->userWith('secretaria', ['users.assign_roles', 'users.view_any'], requiresAssociationMember: true);
        $this->grant($actor, 'socio');
        $plain = UserModel::factory()->create();

        $policy = $this->policy();

        $this->assertTrue($policy->canGrant($actor, 'socio'));
        $this->assertTrue($policy->canChangeRoles($actor, $plain, ['member'], ['member', 'socio']));
        $this->assertFalse($policy->canChangeRoles($actor, $target, ['member', 'presidencia'], ['member', 'presidencia', 'socio']));

        $this->expectException(RoleGrantDeniedException::class);
        $this->expectExceptionMessage('Presidencia');

        $policy->assertCanChangeRoles($actor, $target, ['member', 'presidencia'], ['member', 'presidencia', 'socio']);
    }

    public function test_an_inactive_actor_grants_nothing_from_the_inactive_role(): void
    {
        $this->role('contenido', ['articles.view_any']);
        $this->role('tesoreria', ['settings.manage']);
        $inactive = $this->userWith('secretaria', ['users.assign_roles', 'articles.view_any'], requiresAssociationMember: true);
        $partly = $this->userWith('gestion-usuarios', ['users.assign_roles', 'articles.view_any']);
        $this->grant($partly, 'tesoreria-inactiva', ['settings.manage'], requiresAssociationMember: true);

        $policy = $this->policy();

        $this->assertFalse($policy->canGrant($inactive, 'contenido'));
        $this->assertSame([], $policy->grantableRoleNames($inactive));
        $this->assertTrue($policy->canGrant($partly, 'contenido'));
        $this->assertFalse($policy->canGrant($partly, 'tesoreria'));
    }

    public function test_account_with_a_role_beyond_the_actor_is_not_manageable(): void
    {
        $actor = $this->userWith('secretaria', ['users.assign_roles', 'articles.view_any']);
        $treasurer = $this->userWith('tesoreria', ['settings.manage']);
        $dormantTreasurer = $this->userWith('tesoreria-socia', ['settings.manage'], requiresAssociationMember: true);
        $admin = UserModel::factory()->admin()->create();
        $adminActor = UserModel::factory()->admin()->create();

        $policy = $this->policy();

        $this->assertFalse($policy->canManageAccount($actor, $treasurer));
        $this->assertFalse($policy->canManageAccount($actor, $dormantTreasurer));
        $this->assertFalse($policy->canManageAccount($actor, $admin));
        $this->assertTrue($policy->canManageAccount($adminActor, $admin));
        $this->assertTrue($policy->canManageAccount($adminActor, $treasurer));
    }

    public function test_an_account_whose_roles_give_only_what_the_actor_holds_is_manageable(): void
    {
        $actor = $this->userWith('secretaria', ['users.update', 'articles.view_any', 'events.view_any']);
        $target = UserModel::factory()->create();
        $this->grant($target, 'member', ['events.view_any']);
        $this->grant($target, 'socio', ['articles.view_any']);
        $this->grant($target, 'contenido', ['articles.view_any']);

        $policy = $this->policy();

        $this->assertTrue($policy->canManageAccount($actor, $target));
        $this->assertTrue($policy->canManageAccount($actor, UserModel::factory()->create()));
    }

    public function test_an_account_whose_association_role_gives_more_than_the_actor_holds_is_not_manageable(): void
    {
        $actor = $this->userWith('secretaria', ['users.update', 'users.assign_roles', 'articles.view_any']);
        $target = UserModel::factory()->create();
        $this->grant($target, 'member');
        $this->grant($target, 'socio', ['settings.manage']);

        $this->assertFalse($this->policy()->canManageAccount($actor, $target));
    }

    public function test_an_account_whose_base_role_gives_more_than_the_actor_holds_is_not_manageable(): void
    {
        $actor = $this->userWith('secretaria', ['users.update', 'articles.view_any']);
        $target = UserModel::factory()->create();
        $this->grant($target, 'member', ['settings.manage']);

        $this->assertFalse($this->policy()->canManageAccount($actor, $target));
    }

    public function test_an_admin_account_is_never_manageable_by_a_non_admin(): void
    {
        $adminRole = RoleModel::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $adminRole->permissions()->detach();
        $admin = UserModel::factory()->create();
        $admin->roles()->attach($adminRole->id);
        $actor = $this->userWith('presidencia', ['users.update', 'users.assign_roles', 'users.view_any', 'settings.manage']);

        $this->assertFalse($this->policy()->canManageAccount($actor, $admin));
    }

    public function test_an_account_with_an_unreadable_role_is_not_manageable(): void
    {
        $actor = $this->userWith('secretaria', ['users.update', 'articles.view_any']);
        $target = UserModel::factory()->create();
        $this->grant($target, 'legacy_role');

        $this->assertFalse($this->policy()->canManageAccount($actor, $target));
    }

    public function test_a_role_with_an_unreadable_name_is_grantable_by_an_admin_only(): void
    {
        // A stored name the domain does not accept (not kebab-case) must not break the listing
        RoleModel::query()->create(['name' => 'Juego_Libre', 'display_name' => 'Juego libre']);
        $admin = UserModel::factory()->admin()->create();
        $actor = $this->userWith('secretaria', ['users.assign_roles', 'articles.view_any']);

        $policy = $this->policy();

        $this->assertTrue($policy->canGrant($admin, 'Juego_Libre'));
        $this->assertFalse($policy->canGrant($admin, 'Not_Stored'));
        $this->assertContains('Juego_Libre', $policy->grantableRoleNames($admin));
        $this->assertFalse($policy->canGrant($actor, 'Juego_Libre'));
        $this->assertNotContains('Juego_Libre', $policy->grantableRoleNames($actor));
        $this->assertContains('member', $policy->grantableRoleNames($actor));
    }

    public function test_removals_are_computed_from_the_persisted_roles(): void
    {
        $this->role('tesoreria', ['settings.manage']);
        $actor = $this->userWith('secretaria', ['users.assign_roles', 'articles.view_any']);
        $target = $this->userWith('tesoreria', []);

        $policy = $this->policy();

        // A forged "before" state without tesoreria cannot hide that it is being taken away.
        $this->assertFalse($policy->canChangeRoles($actor, $target, ['member'], ['member']));
        $this->assertFalse($policy->canChangeRoles($actor, $target, [], []));
        // A forged "before" state with tesoreria cannot make it look already held.
        $this->assertFalse($policy->canChangeRoles($actor, UserModel::factory()->create(), ['tesoreria'], ['tesoreria']));
    }

    public function test_an_unsaved_account_starts_from_the_given_roles(): void
    {
        $this->role('contenido', ['articles.view_any']);
        $this->role('tesoreria', ['settings.manage']);
        $actor = $this->userWith('secretaria', ['users.assign_roles', 'articles.view_any']);
        $unsaved = UserModel::factory()->make();

        $policy = $this->policy();

        $this->assertTrue($policy->canChangeRoles($actor, $unsaved, ['member'], ['member', 'contenido']));
        $this->assertFalse($policy->canChangeRoles($actor, $unsaved, ['member'], ['member', 'tesoreria']));
        $this->assertTrue($policy->canChangeRoles($actor, $unsaved, ['member'], ['member', 'socio']));
    }

    public function test_an_unreadable_role_blocks_granting_the_association_role(): void
    {
        $actor = $this->userWith('secretaria', ['users.assign_roles', 'articles.view_any']);
        $target = UserModel::factory()->create();
        $this->grant($target, 'legacy_role');

        $policy = $this->policy();

        $this->assertFalse($policy->canChangeRoles($actor, $target, ['legacy_role'], ['legacy_role', 'socio']));

        $this->expectException(RoleGrantDeniedException::class);
        $this->expectExceptionMessage('legacy_role');

        $policy->assertCanChangeRoles($actor, $target, ['legacy_role'], ['legacy_role', 'socio']);
    }

    public function test_an_unreadable_association_role_setting_checks_the_dormant_roles_on_any_addition(): void
    {
        $this->role('contenido', ['articles.view_any']);
        $this->role('presidencia', ['settings.manage'], requiresAssociationMember: true);
        $actor = $this->userWith('secretaria', ['users.assign_roles', 'articles.view_any']);
        $dormant = $this->userWith('presidencia', []);
        $plain = UserModel::factory()->create();
        $this->breakAssociationMembership();

        $policy = $this->policy();

        $this->assertFalse($policy->canChangeRoles($actor, $dormant, ['presidencia'], ['presidencia', 'contenido']));
        $this->assertFalse($policy->canChangeRoles($actor, $dormant, ['presidencia'], ['presidencia', 'socio']));
        $this->assertTrue($policy->canChangeRoles($actor, $plain, [], ['contenido']));
    }

    public function test_an_unreadable_association_role_setting_keeps_account_management_closed(): void
    {
        $actor = $this->userWith('secretaria', ['users.update', 'articles.view_any']);
        $target = UserModel::factory()->create();
        $this->grant($target, 'socio', ['settings.manage']);
        $plain = UserModel::factory()->create();
        $this->grant($plain, 'contenido', ['articles.view_any']);
        $this->breakAssociationMembership();

        $policy = $this->policy();

        $this->assertFalse($policy->canManageAccount($actor, $target));
        $this->assertTrue($policy->canManageAccount($actor, $plain));
    }

    private function breakAssociationMembership(): void
    {
        Log::spy();
        $this->app->instance(AssociationMembershipServiceInterface::class, new class implements AssociationMembershipServiceInterface
        {
            public function roleName(): ?string
            {
                throw new RuntimeException('Settings unreadable.');
            }

            public function isAssociationMember(object $user): bool
            {
                throw new RuntimeException('Settings unreadable.');
            }
        });
    }

    private function policy(): RoleGrantPolicy
    {
        return app(RoleGrantPolicy::class);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function role(string $name, array $permissionKeys, bool $requiresAssociationMember = false): RoleModel
    {
        $role = RoleModel::query()->firstOrCreate(
            ['name' => $name],
            [
                'display_name' => $this->displayName($name),
                'requires_association_member' => $requiresAssociationMember,
            ],
        );
        $this->attach($role, $permissionKeys);

        return $role;
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function userWith(string $roleName, array $permissionKeys, bool $requiresAssociationMember = false): UserModel
    {
        $user = UserModel::factory()->create();
        $this->grant($user, $roleName, $permissionKeys, $requiresAssociationMember);

        return $user;
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function grant(UserModel $user, string $roleName, array $permissionKeys = [], bool $requiresAssociationMember = false): void
    {
        $role = $this->role($roleName, $permissionKeys, $requiresAssociationMember);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function attach(RoleModel $role, array $permissionKeys): void
    {
        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => $resource, 'action' => $action],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    private function displayName(string $name): string
    {
        return match ($name) {
            'tesoreria' => 'Tesorería',
            'presidencia' => 'Presidencia',
            'secretaria' => 'Secretaría',
            default => ucfirst($name),
        };
    }
}
