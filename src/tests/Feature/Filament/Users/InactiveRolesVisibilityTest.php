<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Widgets\InactiveRolesWidget;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Pages\Page;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * Roles that need association membership are shown as inactive where the account is not a
 * member: in the role list of the form, the users table, its filter and the dashboard.
 */
final class InactiveRolesVisibilityTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private const array PERMISSIONS = [
        'users.view_any',
        'users.view',
        'users.update',
        'users.assign_roles',
        'events.create',
    ];

    public function test_form_marks_inactive_roles(): void
    {
        $this->actingAs($this->editorWithPermissions(self::PERMISSIONS));
        $board = $this->role('board', requiresMember: true, maxHolders: 3, description: 'Runs the guild');
        $plain = $this->role('helper');
        $holder = $this->holder([$board]);

        $field = $this->rolesField(Livewire::test(EditUser::class, ['record' => $holder->getRouteKey()]));

        $this->assertSame('Runs the guild · Requiere socio · Máx. 3 · Inactivo: no es socio', $field->getDescription('board'));
        $this->assertNull($field->getDescription($plain->name));

        $member = $this->holder([$board, $this->socioRole()]);
        $field = $this->rolesField(Livewire::test(EditUser::class, ['record' => $member->getRouteKey()]));

        $this->assertSame('Runs the guild · Requiere socio · Máx. 3', $field->getDescription('board'));
    }

    public function test_filter_lists_accounts_with_inactive_roles(): void
    {
        $this->actingAs($this->editorWithPermissions(self::PERMISSIONS));
        $board = $this->role('board', requiresMember: true);
        $inactive = $this->holder([$board]);
        $active = $this->holder([$board, $this->socioRole()]);
        $plain = $this->holder([$this->role('helper')]);

        Livewire::test(ListUsers::class)
            ->filterTable('inactive_roles', true)
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$active, $plain]);
    }

    public function test_widget_shows_only_to_who_could_grant_and_only_when_any(): void
    {
        $board = $this->role('board', requiresMember: true, permissions: ['events.create']);
        $granter = $this->editorWithPermissions(self::PERMISSIONS);
        $noAssign = $this->editorWithPermissions(['users.view_any', 'events.create']);

        // Nobody holds an inactive role yet
        $this->actingAs($granter);
        $this->assertFalse(InactiveRolesWidget::canView());

        $this->holder([$board]);

        $this->assertTrue(InactiveRolesWidget::canView());

        $this->actingAs($noAssign);
        $this->assertFalse(InactiveRolesWidget::canView());
    }

    public function test_widget_does_not_show_a_role_the_viewer_cannot_grant(): void
    {
        $beyond = $this->role('board', requiresMember: true, permissions: ['events.unheld_permission']);
        $this->holder([$beyond]);

        $this->actingAs($this->editorWithPermissions(self::PERMISSIONS));

        $this->assertFalse(InactiveRolesWidget::canView());
    }

    public function test_widget_links_to_the_user(): void
    {
        $board = $this->role('board', requiresMember: true, permissions: ['events.create']);
        $holder = $this->holder([$board]);
        $this->actingAs($this->editorWithPermissions(self::PERMISSIONS));

        Livewire::test(InactiveRolesWidget::class)
            ->assertCanSeeTableRecords([$holder])
            ->assertSee(UserResource::getUrl('edit', ['record' => $holder]), false)
            ->assertSee($board->display_name);
    }

    /**
     * @param  list<RoleModel>  $roles
     */
    private function holder(array $roles): UserModel
    {
        $user = UserModel::factory()->create();
        $user->roles()->attach(RoleModel::query()->where('name', 'member')->sole()->id);

        foreach ($roles as $role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function socioRole(): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function role(string $name, bool $requiresMember = false, ?int $maxHolders = null, ?string $description = null, array $permissions = []): RoleModel
    {
        $role = RoleModel::query()->updateOrCreate(['name' => $name], [
            'display_name' => ucfirst($name),
            'description' => $description,
            'max_holders' => $maxHolders,
            'requires_association_member' => $requiresMember,
        ]);

        foreach ($permissions as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::query()->firstOrCreate(['key' => $key], ['label' => $key, 'resource' => $resource, 'action' => $action]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $role;
    }

    /**
     * @param  Testable<Component>  $page
     */
    private function rolesField(Testable $page): CheckboxList
    {
        $instance = $page->instance();
        $this->assertInstanceOf(Page::class, $instance);
        $form = $instance->getSchema('form');
        $this->assertNotNull($form);
        $field = $form->getFlatFields(withHidden: true)['roles'] ?? null;
        $this->assertInstanceOf(CheckboxList::class, $field);

        return $field;
    }
}
