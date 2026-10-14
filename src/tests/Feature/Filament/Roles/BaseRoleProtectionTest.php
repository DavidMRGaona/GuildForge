<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Application\Authorization\Services\RoleServiceInterface;
use App\Domain\Authorization\Exceptions\RoleProtectedException;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * "member" is the base role of every account and modules grant it what any signed-in user
 * needs: deleting or renaming it would strip every account of it at once. The rule goes by
 * the role name, not by the "protected" flag, which the base role does not carry.
 */
final class BaseRoleProtectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    // Behaviour that must not change

    public function test_the_admin_role_stays_protected(): void
    {
        $admin = $this->role('admin');
        $admin->update(['is_protected' => true]);

        $this->assertFalse($this->admin->can('delete', $admin));
        $this->assertFalse($this->admin->can('forceDelete', $admin));
        Livewire::test(ListRoles::class)->assertTableActionHidden(DeleteAction::class, $admin);
        Livewire::test(EditRole::class, ['record' => $admin->getRouteKey()])
            ->assertActionHidden(DeleteAction::class)
            ->assertFormFieldIsDisabled('name');
    }

    public function test_the_permissions_and_display_name_of_the_base_role_stay_editable(): void
    {
        $member = $this->member();
        $permission = PermissionModel::query()->create([
            'key' => 'events.create',
            'label' => 'events.create',
            'resource' => 'events',
            'action' => 'create',
        ]);

        Livewire::test(EditRole::class, ['record' => $member->getRouteKey()])
            ->assertFormFieldIsEnabled('display_name')
            ->assertFormFieldIsEnabled('permissions')
            ->fillForm(['display_name' => 'Usuario registrado', 'permissions' => [$permission->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertSame('member', $member->name);
        $this->assertSame('Usuario registrado', $member->display_name);
        $this->assertSame(['events.create'], $member->permissions()->pluck('key')->all());
    }

    public function test_the_role_table_has_no_bulk_delete(): void
    {
        // Rows are deleted one by one, where the base role has no delete action
        $page = Livewire::test(ListRoles::class)->instance();
        $this->assertInstanceOf(ListRoles::class, $page);

        $this->assertSame([], $page->getTable()->getFlatBulkActions());
        $this->assertFalse($page->getTable()->isSelectionEnabled());
    }

    // The base role

    public function test_the_policy_refuses_to_delete_the_base_role(): void
    {
        $member = $this->member();

        $this->assertFalse($member->is_protected);
        $this->assertFalse($this->admin->can('delete', $member));
        $this->assertFalse($this->admin->can('forceDelete', $member));
    }

    public function test_the_base_role_has_no_delete_action_in_the_table(): void
    {
        Livewire::test(ListRoles::class)->assertTableActionHidden(DeleteAction::class, $this->member());
    }

    public function test_the_base_role_has_no_delete_action_in_the_edit_page(): void
    {
        Livewire::test(EditRole::class, ['record' => $this->member()->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_a_forced_delete_from_the_table_keeps_the_base_role(): void
    {
        $member = $this->member();

        Livewire::test(ListRoles::class)
            ->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => $member->getKey()])
            ->call('callMountedAction');

        $this->assertTrue(RoleModel::query()->whereKey($member->id)->exists());
    }

    public function test_a_forced_delete_from_the_edit_page_keeps_the_base_role(): void
    {
        $member = $this->member();

        Livewire::test(EditRole::class, ['record' => $member->getKey()])
            ->call('mountAction', 'delete')
            ->call('callMountedAction');

        $this->assertTrue(RoleModel::query()->whereKey($member->id)->exists());
    }

    public function test_the_base_role_cannot_be_deleted_through_the_role_service(): void
    {
        $member = $this->member();

        try {
            app(RoleServiceInterface::class)->delete($member->id);
            $this->fail('The base role was deleted');
        } catch (RoleProtectedException $e) {
            $this->assertSame(__('authorization.errors.cannot_delete_base_role', ['role' => 'member']), $e->getMessage());
            $this->assertTrue(RoleModel::query()->whereKey($member->id)->exists());
        }
    }

    public function test_the_name_of_the_base_role_is_disabled_and_explained(): void
    {
        Livewire::test(EditRole::class, ['record' => $this->member()->getRouteKey()])
            ->assertFormFieldIsDisabled('name')
            ->assertSee(__('filament.roles.fields.baseRoleHint'));
    }

    public function test_a_forged_rename_of_the_base_role_is_not_saved(): void
    {
        $member = $this->member();

        Livewire::test(EditRole::class, ['record' => $member->getRouteKey()])
            ->set('data.name', 'registrado')
            ->set('data.display_name', 'Registrado')
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertSame('member', $member->name);
        $this->assertSame('Registrado', $member->display_name);
    }

    public function test_the_save_guard_drops_the_name_of_the_base_role_on_its_own(): void
    {
        $page = Livewire::test(EditRole::class, ['record' => $this->member()->getRouteKey()])->instance();
        $this->assertInstanceOf(EditRole::class, $page);

        // Called directly: the guard must hold even if the name field were enabled
        /** @var array<string, mixed> $data */
        $data = (new ReflectionMethod($page, 'mutateFormDataBeforeSave'))
            ->invoke($page, ['name' => 'registrado', 'display_name' => 'Registrado']);

        $this->assertSame(['display_name' => 'Registrado'], $data);
    }

    private function member(): RoleModel
    {
        // Created by a migration, without the protected flag
        return RoleModel::query()->where('name', 'member')->sole();
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
    }
}
