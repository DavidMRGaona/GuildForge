<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Services\UserServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\RolesSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The panel opens for whoever holds admin.access through any role, whatever the role is called;
 * Filament (UserModel) and UserService must give the same answer.
 */
final class PanelAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_a_custom_role_with_admin_access_opens_the_panel(): void
    {
        $user = $this->userWithRole('treasury', ['admin.access']);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue(app(UserServiceInterface::class)->canAccessPanel($user->id));
        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_a_custom_role_with_admin_access_can_log_in_to_the_panel(): void
    {
        $user = $this->userWithRole('treasury', ['admin.access']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_custom_role_without_admin_access_does_not_open_the_panel(): void
    {
        $user = $this->userWithRole('treasury', ['events.view_any']);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse(app(UserServiceInterface::class)->canAccessPanel($user->id));
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_the_editor_role_without_admin_access_does_not_open_the_panel(): void
    {
        $editor = UserModel::factory()->editor()->create();
        RoleModel::query()->where('name', 'editor')->sole()->permissions()->detach(
            PermissionModel::query()->where('key', 'admin.access')->sole()->id,
        );

        $this->assertFalse($editor->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse(app(UserServiceInterface::class)->canAccessPanel($editor->id));
        $this->actingAs($editor)->get('/admin')->assertForbidden();
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    private function userWithRole(string $roleName, array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => $roleName, 'display_name' => $roleName]);
        $role->permissions()->attach(
            PermissionModel::query()->whereIn('key', $permissionKeys)->pluck('id')->all(),
        );

        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
