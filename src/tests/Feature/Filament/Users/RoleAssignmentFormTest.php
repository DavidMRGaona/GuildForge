<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Authorization\Exceptions\RoleGrantDeniedException;
use App\Domain\Authorization\Exceptions\RoleHolderLimitReachedException;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
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
 * The roles of the user form go through the grant rule and the holder limit: nobody gives or
 * takes a role whose permissions they lack, a full role is refused with whoever holds it, and
 * a refused change saves nothing, not even the other fields.
 */
final class RoleAssignmentFormTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private const array FORM_PERMISSIONS = [
        'users.view_any',
        'users.view',
        'users.create',
        'users.update',
        'users.assign_roles',
        'events.create',
    ];

    public function test_forged_admin_role_is_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->editorWithPermissions(self::FORM_PERMISSIONS));
        $this->adminRole();
        $target = $this->userWithMember();

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['display_name' => 'Changed name', 'roles' => ['member', 'admin']])
            ->call('save')
            ->assertNotified(RoleGrantDeniedException::forRoles(['Administrator'])->getMessage());

        $this->assertSame(['member'], $this->roleNamesInDatabase($target));
        $this->assertNotSame('Changed name', $target->fresh()?->display_name);
    }

    public function test_options_the_actor_cannot_grant_are_disabled(): void
    {
        $this->actingAs($this->editorWithPermissions(self::FORM_PERMISSIONS));
        $this->adminRole();
        $grantable = $this->roleWith('game-master', ['events.create']);
        $beyond = $this->roleWith('board', ['events.unheld_permission']);
        $target = $this->userWithMember();

        $field = $this->rolesField(Livewire::test(EditUser::class, ['record' => $target->getRouteKey()]));

        $this->assertTrue($field->isOptionDisabled('admin', 'Administrator'));
        $this->assertTrue($field->isOptionDisabled($beyond->name, $beyond->display_name));
        $this->assertTrue($field->isOptionDisabled('member', 'Member'));
        $this->assertFalse($field->isOptionDisabled($grantable->name, $grantable->display_name));
    }

    public function test_grantable_role_is_assigned_through_the_service(): void
    {
        $this->actingAs($this->editorWithPermissions(self::FORM_PERMISSIONS));
        $role = $this->roleWith('game-master', ['events.create']);
        $target = $this->userWithMember();
        // Cached before the change: the save must drop the target's cached roles
        $this->assertFalse($this->authorization()->hasRole($target, $role->name));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => ['member', $role->name]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['game-master', 'member'], $this->roleNamesInDatabase($target));
        $this->assertTrue($this->authorization()->hasRole($target, $role->name));
        $this->assertTrue($this->authorization()->can($target, 'events.create'));
    }

    public function test_grantable_role_is_given_when_creating_an_account(): void
    {
        $this->actingAs($this->editorWithPermissions(self::FORM_PERMISSIONS));
        $role = $this->roleWith('game-master', ['events.create']);

        Livewire::test(CreateUser::class)
            ->fillForm($this->newAccount(['member', $role->name]))
            ->call('create')
            ->assertHasNoFormErrors();

        $created = UserModel::query()->where('email', 'new.account@example.com')->sole();
        $this->assertEqualsCanonicalizing(['game-master', 'member'], $this->roleNamesInDatabase($created));
    }

    public function test_forged_role_on_a_new_account_creates_nothing(): void
    {
        $this->actingAs($this->editorWithPermissions(self::FORM_PERMISSIONS));
        $this->adminRole();

        Livewire::test(CreateUser::class)
            ->fillForm($this->newAccount(['member', 'admin']))
            ->call('create')
            ->assertNotified(RoleGrantDeniedException::forRoles(['Administrator'])->getMessage());

        $this->assertFalse(UserModel::query()->where('email', 'new.account@example.com')->exists());
    }

    public function test_without_assign_roles_the_roles_in_the_state_are_ignored(): void
    {
        $this->actingAs($this->editorWithPermissions(['users.view_any', 'users.view', 'users.update', 'events.create']));
        $role = $this->roleWith('game-master', ['events.create']);
        $target = $this->userWithMember();
        $target->roles()->attach($role->id);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormFieldDisabled('roles')
            ->fillForm(['display_name' => 'Changed name'])
            ->set('data.roles', ['member', 'editor'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['game-master', 'member'], $this->roleNamesInDatabase($target));
        $this->assertSame('Changed name', $target->fresh()?->display_name);
    }

    public function test_without_assign_roles_a_new_account_only_gets_the_base_role(): void
    {
        $this->actingAs($this->editorWithPermissions(['users.view_any', 'users.view', 'users.create', 'events.create']));
        $role = $this->roleWith('game-master', ['events.create']);

        Livewire::test(CreateUser::class)
            ->fillForm($this->newAccount([]))
            ->set('data.roles', ['member', $role->name])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = UserModel::query()->where('email', 'new.account@example.com')->sole();
        $this->assertSame(['member'], $this->roleNamesInDatabase($created));
    }

    public function test_full_role_shows_the_holder_and_saves_nothing(): void
    {
        $this->actingAs($this->editorWithPermissions(self::FORM_PERMISSIONS));
        $role = $this->roleWith('treasurer', ['events.create'], maxHolders: 1);
        $holder = UserModel::factory()->create(['display_name' => 'Jane Doe']);
        $holder->roles()->attach($role->id);
        $target = $this->userWithMember();

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['display_name' => 'Changed name', 'roles' => ['member', $role->name]])
            ->call('save')
            ->assertNotified(RoleHolderLimitReachedException::forRole('Treasurer', ['Jane Doe'])->getMessage());

        $this->assertSame(['member'], $this->roleNamesInDatabase($target));
        $this->assertNotSame('Changed name', $target->fresh()?->display_name);
    }

    /**
     * Through the edit page this cannot happen: an account holding a role beyond the actor is
     * not manageable, so every request of its page is refused (UserPolicy). A new account is
     * where the association role and such a role can be ticked together.
     */
    public function test_manual_association_role_is_rejected_when_it_would_wake_a_role_beyond_the_actor(): void
    {
        $this->actingAs($this->editorWithPermissions(self::FORM_PERMISSIONS));
        $socio = $this->roleWith('socio', []);
        $board = $this->roleWith('board', ['events.unheld_permission'], requiresAssociationMember: true);

        Livewire::test(CreateUser::class)
            ->fillForm($this->newAccount(['member', $socio->name]))
            ->set('data.roles', ['member', $socio->name, $board->name])
            ->call('create')
            ->assertNotified(RoleGrantDeniedException::forRoles(['Board'])->getMessage());

        $this->assertFalse(UserModel::query()->where('email', 'new.account@example.com')->exists());
    }

    public function test_base_role_stays_ticked_and_cannot_be_removed(): void
    {
        $this->actingAs($this->editorWithPermissions(self::FORM_PERMISSIONS));
        $role = $this->roleWith('game-master', ['events.create']);
        $target = $this->userWithMember();

        $page = Livewire::test(EditUser::class, ['record' => $target->getRouteKey()]);

        $this->assertContains('member', $page->get('data.roles'));
        $this->assertTrue($this->rolesField($page)->isOptionDisabled('member', 'Member'));

        $page->set('data.roles', [$role->name])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['game-master', 'member'], $this->roleNamesInDatabase($target));
    }

    public function test_admin_keeps_full_control(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $board = $this->roleWith('board', ['events.unheld_permission']);
        $target = $this->userWithMember();
        $target->roles()->attach($board->id);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => ['member', 'admin']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['admin', 'member'], $this->roleNamesInDatabase($target));
    }

    public function test_header_account_actions_need_users_delete(): void
    {
        $this->actingAs($this->editorWithPermissions(['users.view_any', 'users.view', 'users.update']));
        $active = $this->userWithMember();
        $deactivated = $this->userWithMember();
        $deactivated->delete();

        Livewire::test(EditUser::class, ['record' => $active->getRouteKey()])
            ->assertActionHidden('deactivate');
        Livewire::test(EditUser::class, ['record' => $deactivated->getRouteKey()])
            ->assertActionHidden('anonymize');
    }

    /**
     * @param  list<string>  $roles
     * @return array<string, mixed>
     */
    private function newAccount(array $roles): array
    {
        return [
            'name' => 'new-account',
            'email' => 'new.account@example.com',
            'password' => 'a-long-password',
            'roles' => $roles,
        ];
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

    /**
     * @return list<string>
     */
    private function roleNamesInDatabase(UserModel $user): array
    {
        /** @var list<string> $names */
        $names = $user->roles()->pluck('name')->all();

        return $names;
    }

    private function userWithMember(): UserModel
    {
        $user = UserModel::factory()->create();
        $user->roles()->attach(RoleModel::query()->where('name', 'member')->sole()->id);

        return $user;
    }

    private function adminRole(): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function roleWith(string $name, array $permissionKeys, ?int $maxHolders = null, bool $requiresAssociationMember = false): RoleModel
    {
        $role = RoleModel::query()->updateOrCreate(['name' => $name], [
            'display_name' => ucfirst($name),
            'max_holders' => $maxHolders,
            'requires_association_member' => $requiresAssociationMember,
        ]);

        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::query()->firstOrCreate(['key' => $key], ['label' => $key, 'resource' => $resource, 'action' => $action]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $role;
    }

    private function authorization(): AuthorizationServiceInterface
    {
        return app(AuthorizationServiceInterface::class);
    }
}
