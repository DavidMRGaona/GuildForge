<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
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
