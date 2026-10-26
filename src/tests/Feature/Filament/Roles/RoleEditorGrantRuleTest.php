<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class RoleEditorGrantRuleTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_non_admin_cannot_add_a_permission_they_lack(): void
    {
        $actor = $this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.update', 'events.view_any']);
        $role = $this->roleWith(['events.view_any']);
        $forbidden = $this->permission('users.delete');
        $this->actingAs($actor);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions', [...$this->ids($role), $forbidden->id])
            ->call('save')
            ->assertNotified();

        $this->assertSame(['events.view_any'], $this->keys($role));
    }

    public function test_non_admin_cannot_remove_a_permission_they_lack(): void
    {
        // A stale or forged state: the role gained a permission beyond the actor's after the page opened
        $actor = $this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.update', 'events.view_any']);
        $role = $this->roleWith(['events.view_any']);
        $this->actingAs($actor);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()]);
        $role->permissions()->attach($this->permission('users.delete')->id);

        // The role now goes beyond the actor: the policy refuses to open it again
        $this->assertFalse($actor->can('update', $role));
        $this->get(EditRole::getUrl(['record' => $role]))->assertForbidden();

        $this->assertEqualsCanonicalizing(['events.view_any', 'users.delete'], $this->keys($role));
    }

    public function test_non_admin_can_edit_permissions_they_hold(): void
    {
        $actor = $this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.update', 'events.view_any', 'events.create']);
        $role = $this->roleWith(['events.view_any']);
        $this->actingAs($actor);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions', [...$this->ids($role), $this->permission('events.create')->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['events.view_any', 'events.create'], $this->keys($role));
    }

    public function test_non_admin_cannot_open_a_role_beyond_their_permissions(): void
    {
        $actor = $this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.update', 'events.view_any']);
        $beyond = $this->roleWith(['events.view_any', 'users.delete']);
        $within = $this->roleWith(['events.view_any']);

        $this->assertFalse($actor->can('update', $beyond));
        $this->assertTrue($actor->can('update', $within));
    }

    public function test_admin_access_cannot_be_added_to_the_association_role(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $socio = RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $this->actingAs($admin);

        Livewire::test(EditRole::class, ['record' => $socio->getRouteKey()])
            ->set('data.permissions', [$this->permission('admin.access')->id])
            ->call('save')
            ->assertNotified();

        $this->assertSame([], $this->keys($socio));
    }

    public function test_admin_access_cannot_be_added_to_the_base_role(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $member = RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Miembro']);

        Livewire::test(EditRole::class, ['record' => $member->getRouteKey()])
            ->set('data.permissions', [$this->permission('admin.access')->id])
            ->call('save')
            ->assertNotified();

        $this->assertNotContains('admin.access', $this->keys($member));
    }

    public function test_a_role_cannot_be_renamed_into_the_association_role_while_holding_admin_access(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        // The configured role does not exist yet: any role renamed to it would become it
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'junta');
        $role = $this->roleWith(['admin.access']);
        $oldName = $role->name;

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.name', 'junta')
            ->call('save')
            ->assertNotified();

        $this->assertSame($oldName, $role->refresh()->name);
    }

    public function test_an_unknown_permission_id_is_refused_for_everyone(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->roleWith(['events.view_any']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions', [...$this->ids($role), '00000000-0000-4000-8000-000000000000'])
            ->call('save')
            ->assertHasErrors();

        $this->assertSame(['events.view_any'], $this->keys($role));
    }

    public function test_non_admin_cannot_create_a_role_with_permissions_they_lack(): void
    {
        $this->actingAs($this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.create', 'events.view_any']));
        $forbidden = $this->permission('users.delete');

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'nuevo-rol', 'display_name' => 'Nuevo', 'permissions' => [$forbidden->id]])
            ->call('create')
            ->assertNotified();

        $this->assertFalse(RoleModel::query()->where('name', 'nuevo-rol')->exists());
    }

    public function test_non_admin_cannot_delete_a_role_beyond_their_permissions(): void
    {
        $actor = $this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.delete', 'events.view_any']);
        $beyond = $this->roleWith(['events.view_any', 'users.delete']);
        $within = $this->roleWith(['events.view_any']);
        $this->actingAs($actor);

        $this->assertFalse($actor->can('delete', $beyond));
        $this->assertFalse($actor->can('forceDelete', $beyond));
        $this->assertTrue($actor->can('delete', $within));

        Livewire::test(ListRoles::class)
            ->assertTableActionHidden(DeleteAction::class, $beyond)
            ->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => $beyond->getKey()])
            ->call('callMountedAction');

        $this->assertTrue(RoleModel::query()->whereKey($beyond->id)->exists());
    }

    public function test_a_legacy_role_name_does_not_block_other_edits(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = RoleModel::query()->create(['name' => 'Legacy_Name', 'display_name' => 'Legacy']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['display_name' => 'Heredado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Heredado', $role->refresh()->display_name);
    }

    public function test_admin_access_cannot_be_given_on_create_to_the_association_role(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'junta');

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'junta', 'display_name' => 'Junta', 'permissions' => [$this->permission('admin.access')->id]])
            ->call('create')
            ->assertNotified();

        $this->assertFalse(RoleModel::query()->where('name', 'junta')->exists());
    }

    public function test_admin_edits_any_role(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->roleWith(['events.view_any']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions', [$this->permission('users.delete')->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['users.delete'], $this->keys($role));
    }

    public function test_the_role_name_must_be_kebab_case(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'Juego_Libre', 'display_name' => 'Juego libre'])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertFalse(RoleModel::query()->where('name', 'Juego_Libre')->exists());
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
