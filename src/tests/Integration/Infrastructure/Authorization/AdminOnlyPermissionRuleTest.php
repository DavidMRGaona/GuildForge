<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Authorization;

use App\Application\Authorization\DTOs\CreateRoleDTO;
use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\DTOs\UpdateRoleDTO;
use App\Application\Authorization\Services\AdminOnlyPermissionRule;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Application\Authorization\Services\RolePermissionGrantsInterface;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Domain\Authorization\Exceptions\RoleProtectedException;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Installing modules and applying updates run code on the server: those permissions belong to
 * the administrator role alone, so whoever may create roles can never build another administrator.
 */
final class AdminOnlyPermissionRuleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_module_management_and_updates_are_admin_only(): void
    {
        $rule = app(AdminOnlyPermissionRule::class);

        $this->assertTrue($rule->isAdminOnly('modules.manage'));
        $this->assertTrue($rule->isAdminOnly('updates.apply'));
    }

    public function test_maintenance_module_settings_and_mail_are_not_admin_only(): void
    {
        $rule = app(AdminOnlyPermissionRule::class);

        $this->assertFalse($rule->isAdminOnly('settings.maintenance'));
        $this->assertFalse($rule->isAdminOnly('modules.configure'));
        $this->assertFalse($rule->isAdminOnly('mail.configure'));
        $this->assertFalse($rule->isAdminOnly('roles.create'));
    }

    public function test_core_keys_stay_admin_only_without_their_definitions(): void
    {
        // Fails closed: the list does not depend on what the registry holds
        app(PermissionRegistryInterface::class)->clear();

        $this->assertTrue(app(AdminOnlyPermissionRule::class)->isAdminOnly('modules.manage'));
    }

    public function test_a_module_can_mark_its_own_permission_as_admin_only(): void
    {
        app(PermissionRegistryInterface::class)->register(new PermissionDefinitionDTO(
            key: 'mymodule:servers.run',
            label: 'Run servers',
            resource: 'servers',
            action: 'run',
            module: 'mymodule',
            adminOnly: true,
        ));

        $this->assertTrue(app(AdminOnlyPermissionRule::class)->isAdminOnly('mymodule:servers.run'));
    }

    public function test_only_the_admin_role_may_hold_them(): void
    {
        $rule = app(AdminOnlyPermissionRule::class);

        $this->assertTrue($rule->mayHold('admin', 'modules.manage'));
        $this->assertFalse($rule->mayHold('presidencia', 'modules.manage'));
        $this->assertTrue($rule->mayHold('presidencia', 'events.create'));
        $this->assertSame(['updates.apply'], $rule->notAllowedFor('junta', ['events.create', 'updates.apply']));
        $this->assertSame([], $rule->notAllowedFor('admin', ['events.create', 'updates.apply']));
    }

    public function test_a_stored_admin_only_permission_gives_nothing_outside_the_admin_role(): void
    {
        $role = $this->role('junta', ['admin.access', 'modules.manage', 'events.create']);
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $authorization = app(AuthorizationServiceInterface::class);

        $this->assertFalse($authorization->can($user, 'modules.manage'));
        $this->assertTrue($authorization->can($user, 'events.create'));
        $this->assertEqualsCanonicalizing(['admin.access', 'events.create'], $authorization->getPermissions($user));
        // So the role editor never lets them tick it either
        $this->assertNotContains('modules.manage', app(RoleGrantPolicy::class)->editablePermissionKeys($user) ?? []);
    }

    public function test_the_admin_keeps_them(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $authorization = app(AuthorizationServiceInterface::class);

        $this->assertTrue($authorization->can($admin, 'modules.manage'));
        $this->assertTrue($authorization->can($admin, 'updates.apply'));
    }

    public function test_role_service_refuses_them_when_creating_a_role(): void
    {
        $this->permission('modules.manage');

        try {
            app(RoleServiceInterface::class)->create(new CreateRoleDTO(
                name: 'junta',
                displayName: 'Junta',
                permissionKeys: ['modules.manage'],
            ));
            $this->fail('An admin-only permission was accepted on a new role.');
        } catch (RoleProtectedException) {
            $this->assertFalse(RoleModel::query()->where('name', 'junta')->exists());
        }
    }

    public function test_role_service_refuses_them_when_updating_assigning_or_syncing(): void
    {
        $role = $this->role('junta', ['events.create']);
        $this->permission('updates.apply');
        $service = app(RoleServiceInterface::class);

        $attempts = [
            static fn () => $service->update($role->id, new UpdateRoleDTO(permissionKeys: ['updates.apply'])),
            static fn () => $service->assignPermissions($role->id, ['updates.apply']),
            static fn () => $service->syncPermissions($role->id, ['events.create', 'updates.apply']),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('An admin-only permission was accepted on a regular role.');
            } catch (RoleProtectedException) {
                $this->assertSame(['events.create'], $this->keys($role));
            }
        }
    }

    public function test_role_service_accepts_them_on_the_admin_role(): void
    {
        $admin = $this->role('admin', []);
        $this->permission('updates.apply');

        app(RoleServiceInterface::class)->assignPermissions($admin->id, ['updates.apply']);

        $this->assertContains('updates.apply', $this->keys($admin));
    }

    public function test_migration_grants_skip_them_outside_the_admin_role_with_a_warning(): void
    {
        $role = $this->role('junta', []);
        $log = Log::spy();

        app(RolePermissionGrantsInterface::class)->grantToRole(
            'junta',
            new PermissionDefinitionDTO(key: 'modules.manage', label: 'Gestionar módulos', resource: 'modules', action: 'manage'),
            new PermissionDefinitionDTO(key: 'events.create', label: 'Crear eventos', resource: 'events', action: 'create'),
        );

        $this->assertSame(['events.create'], $this->keys($role));
        $log->shouldHaveReceived('warning')->once();
    }

    /**
     * @param  list<string>  $keys
     */
    private function role(string $name, array $keys): RoleModel
    {
        $role = RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => $name]);

        foreach ($keys as $key) {
            $role->permissions()->attach($this->permission($key)->id);
        }

        return $role;
    }

    private function permission(string $key): PermissionModel
    {
        [$resource, $action] = explode('.', $key, 2);

        return PermissionModel::query()->firstOrCreate(
            ['key' => $key],
            ['label' => $key, 'resource' => $resource, 'action' => $action],
        );
    }

    /**
     * @return list<string>
     */
    private function keys(RoleModel $role): array
    {
        return array_values(array_map(strval(...), $role->permissions()->pluck('permissions.key')->all()));
    }
}
