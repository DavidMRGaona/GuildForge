<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Authorization\FillsRolePermissionTabs;
use Tests\TestCase;

/**
 * Installing modules and applying updates stay with the administrator role: the role editor
 * never puts them in another role, not even for an admin, so creating roles can be delegated.
 */
final class AdminOnlyPermissionsEditorTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use FillsRolePermissionTabs;
    use LazilyRefreshDatabase;

    public function test_admin_cannot_tick_an_admin_only_permission_on_a_regular_role(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->roleWith(['events.view_any']);

        $this->tickPermissions(Livewire::test(EditRole::class, ['record' => $role->getRouteKey()]), [...$this->ids($role), $this->permission('modules.manage')->id])
            ->call('save')
            ->assertNotified((string) __('filament.roles.notifications.adminOnlyPermissions'));

        $this->assertSame(['events.view_any'], $this->keys($role));
    }

    public function test_admin_cannot_create_a_role_with_an_admin_only_permission(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $page = Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'junta', 'display_name' => 'Junta']);
        $this->tickPermissions($page, [$this->permission('updates.apply')->id])
            ->call('create')
            ->assertNotified((string) __('filament.roles.notifications.adminOnlyPermissions'));

        $this->assertFalse(RoleModel::query()->where('name', 'junta')->exists());
    }

    public function test_the_editor_marks_admin_only_permissions_on_a_regular_role(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->permission('modules.manage');
        $role = $this->roleWith(['events.view_any']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSee((string) __('filament.roles.fields.adminOnlyPermission'));
    }

    public function test_saving_a_regular_role_drops_a_stored_admin_only_permission_and_logs_it(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        // Written before the rule existed
        $role = $this->roleWith(['events.view_any', 'modules.manage']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['display_name' => 'Junta directiva'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['events.view_any'], $this->keys($role));
        $entry = RoleAuditEntryModel::query()->where('role_id', $role->id)->latest('created_at')->firstOrFail();
        $this->assertSame(['modules.manage'], $entry->changes['permissions_removed'] ?? null);
    }

    public function test_someone_who_may_create_roles_builds_one_and_can_grant_it(): void
    {
        $actor = $this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.create', 'users.assign_roles', 'events.view_any', 'events.create']);
        $this->actingAs($actor);

        $page = Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'gestion-web', 'display_name' => 'Gestión de la web']);
        $this->tickPermissions($page, [$this->permission('events.view_any')->id, $this->permission('events.create')->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = RoleModel::query()->where('name', 'gestion-web')->firstOrFail();
        $this->assertEqualsCanonicalizing(['events.view_any', 'events.create'], $this->keys($role));
        $grants = app(RoleGrantPolicy::class);
        $this->assertTrue($grants->canGrant($actor, 'gestion-web'));
        $this->assertFalse($grants->canGrant($actor, 'admin'));
    }

    public function test_someone_who_may_create_roles_cannot_create_another_admin_role(): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administración']);
        $this->actingAs($this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.create', 'events.view_any']));

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'admin', 'display_name' => 'Otra administración'])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertSame(1, RoleModel::query()->where('name', 'admin')->count());
    }

    /**
     * @param  list<string>  $keys
     */
    private function roleWith(array $keys): RoleModel
    {
        $role = RoleModel::query()->create(['name' => 'role-'.uniqid(), 'display_name' => 'Role']);

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
    private function ids(RoleModel $role): array
    {
        return array_values($role->permissions()->pluck('permissions.id')->map(strval(...))->all());
    }

    /**
     * @return list<string>
     */
    private function keys(RoleModel $role): array
    {
        return array_values(array_map(strval(...), $role->permissions()->pluck('permissions.key')->all()));
    }
}
