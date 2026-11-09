<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Services\UserServiceInterface;
use App\Domain\Events\RolePermissionsChanged;
use App\Domain\Events\UserAnonymized;
use App\Domain\Events\UserDeactivated;
use App\Domain\Events\UserProfileUpdated;
use App\Domain\Events\UserRestored;
use App\Domain\Events\UserRolesChanged;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\Authorization\FillsRolePermissionTabs;
use Tests\TestCase;

/**
 * Modules that mirror accounts elsewhere hear about account and role changes as domain events:
 * once per change, after the commit, and never when the change is rolled back.
 */
final class AccountEventsTest extends TestCase
{
    use FillsRolePermissionTabs;
    use LazilyRefreshDatabase;

    private AuthorizationServiceInterface $authorization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorization = app(AuthorizationServiceInterface::class);
        RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Miembro']);
    }

    public function test_assigning_a_role_dispatches_the_roles_before_and_after(): void
    {
        $user = UserModel::factory()->create();
        $this->role('game-master');
        Event::fake([UserRolesChanged::class]);

        $this->authorization->assignRole($user, 'game-master');

        Event::assertDispatchedTimes(UserRolesChanged::class, 1);
        Event::assertDispatched(
            UserRolesChanged::class,
            static fn (UserRolesChanged $event): bool => $event->userId === $user->id
                && $event->rolesBefore === []
                && $event->rolesAfter === ['game-master'],
        );
    }

    public function test_assigning_a_held_role_dispatches_nothing(): void
    {
        $user = UserModel::factory()->create();
        $this->authorization->assignRole($user, 'member');
        Event::fake([UserRolesChanged::class]);

        $this->authorization->assignRole($user, 'member');

        Event::assertNotDispatched(UserRolesChanged::class);
    }

    public function test_removing_a_role_dispatches_once_and_removing_an_unheld_one_nothing(): void
    {
        $user = UserModel::factory()->create();
        $this->role('game-master');
        $this->role('board');
        $this->authorization->assignRole($user, 'game-master');
        Event::fake([UserRolesChanged::class]);

        $this->authorization->removeRole($user, 'board');
        Event::assertNotDispatched(UserRolesChanged::class);

        $this->authorization->removeRole($user, 'game-master');

        Event::assertDispatchedTimes(UserRolesChanged::class, 1);
        Event::assertDispatched(
            UserRolesChanged::class,
            static fn (UserRolesChanged $event): bool => $event->rolesBefore === ['game-master'] && $event->rolesAfter === [],
        );
    }

    public function test_syncing_roles_dispatches_once_with_the_real_difference(): void
    {
        $user = UserModel::factory()->create();
        $this->role('game-master');
        $this->role('board');
        $this->authorization->syncRoles($user, ['member', 'board']);
        Event::fake([UserRolesChanged::class]);

        $this->authorization->syncRoles($user, ['member', 'board']);
        Event::assertNotDispatched(UserRolesChanged::class);

        $this->authorization->syncRoles($user, ['member', 'game-master']);

        Event::assertDispatchedTimes(UserRolesChanged::class, 1);
        Event::assertDispatched(
            UserRolesChanged::class,
            static fn (UserRolesChanged $event): bool => $event->rolesBefore === ['board', 'member']
                && $event->rolesAfter === ['game-master', 'member'],
        );
    }

    public function test_a_rolled_back_role_change_dispatches_nothing(): void
    {
        $user = UserModel::factory()->create();
        $this->role('game-master');
        Event::fake([UserRolesChanged::class]);

        try {
            DB::transaction(function () use ($user): void {
                $this->authorization->assignRole($user, 'game-master');

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(UserRolesChanged::class);
    }

    public function test_the_user_form_dispatches_one_roles_event(): void
    {
        $this->signInAsAdminOnThePanel();
        $this->role('game-master');
        $target = UserModel::factory()->create();
        app(MemberRoleServiceInterface::class)->assignTo($target);
        Event::fake([UserRolesChanged::class]);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => ['member', 'game-master']])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertDispatchedTimes(UserRolesChanged::class, 1);
    }

    public function test_the_bulk_member_assignment_dispatches_one_event_per_account(): void
    {
        $withRole = UserModel::factory()->create();
        $this->role('board');
        $this->authorization->assignRole($withRole, 'board');
        $without = UserModel::factory()->create();
        Event::fake([UserRolesChanged::class]);

        app(MemberRoleServiceInterface::class)->assignToAllUsers();

        Event::assertDispatchedTimes(UserRolesChanged::class, 2);
        Event::assertDispatched(
            UserRolesChanged::class,
            static fn (UserRolesChanged $event): bool => $event->userId === $withRole->id
                && $event->rolesBefore === ['board']
                && $event->rolesAfter === ['board', 'member'],
        );
        Event::assertDispatched(
            UserRolesChanged::class,
            static fn (UserRolesChanged $event): bool => $event->userId === $without->id
                && $event->rolesBefore === []
                && $event->rolesAfter === ['member'],
        );
    }

    public function test_changing_the_permissions_of_a_role_dispatches_the_keys_before_and_after(): void
    {
        $role = $this->role('game-master');
        $role->permissions()->attach($this->permission('events.view_any')->id);
        $this->permission('events.create');
        Event::fake([RolePermissionsChanged::class]);

        app(RoleServiceInterface::class)->assignPermissions($role->id, ['events.create']);

        Event::assertDispatchedTimes(RolePermissionsChanged::class, 1);
        Event::assertDispatched(
            RolePermissionsChanged::class,
            static fn (RolePermissionsChanged $event): bool => $event->roleId === $role->id
                && $event->roleName === 'game-master'
                && $event->permissionsBefore === ['events.view_any']
                && $event->permissionsAfter === ['events.create', 'events.view_any'],
        );

        app(RoleServiceInterface::class)->syncPermissions($role->id, ['events.create']);

        Event::assertDispatchedTimes(RolePermissionsChanged::class, 2);
    }

    public function test_unchanged_permissions_and_a_rollback_dispatch_nothing(): void
    {
        $role = $this->role('game-master');
        $role->permissions()->attach($this->permission('events.view_any')->id);
        $this->permission('events.create');
        Event::fake([RolePermissionsChanged::class]);

        app(RoleServiceInterface::class)->syncPermissions($role->id, ['events.view_any']);
        Event::assertNotDispatched(RolePermissionsChanged::class);

        try {
            DB::transaction(function () use ($role): void {
                app(RoleServiceInterface::class)->assignPermissions($role->id, ['events.create']);

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(RolePermissionsChanged::class);
    }

    public function test_the_role_form_dispatches_one_permissions_event(): void
    {
        $this->signInAsAdminOnThePanel();
        $role = $this->role('game-master');
        $role->permissions()->attach($this->permission('events.view_any')->id);
        $create = $this->permission('events.create');
        Event::fake([RolePermissionsChanged::class]);

        $this->tickPermissions(Livewire::test(EditRole::class, ['record' => $role->getRouteKey()]), [$create->id])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertDispatchedTimes(RolePermissionsChanged::class, 1);
    }

    public function test_anonymizing_dispatches_one_event_and_no_roles_event(): void
    {
        $service = app(UserServiceInterface::class);
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();
        $this->authorization->assignRole($first, 'member');
        Event::fake([UserAnonymized::class, UserRolesChanged::class]);

        $service->anonymize($first->id);
        $service->anonymizeWithContentTransfer(new AnonymizeUserDTO(userId: $second->id, contentAction: 'anonymize'));

        Event::assertDispatchedTimes(UserAnonymized::class, 2);
        Event::assertDispatched(UserAnonymized::class, static fn (UserAnonymized $event): bool => $event->userId === $first->id);
        Event::assertDispatched(UserAnonymized::class, static fn (UserAnonymized $event): bool => $event->userId === $second->id);
        Event::assertNotDispatched(UserRolesChanged::class);
    }

    public function test_a_rolled_back_anonymization_dispatches_nothing(): void
    {
        $user = UserModel::factory()->create();
        Event::fake([UserAnonymized::class]);

        try {
            DB::transaction(function () use ($user): void {
                app(UserServiceInterface::class)->anonymize($user->id);

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(UserAnonymized::class);
    }

    public function test_deactivating_and_restoring_an_account_dispatch_one_event_each(): void
    {
        $user = UserModel::factory()->create();
        Event::fake([UserDeactivated::class, UserRestored::class]);

        $user->delete();

        Event::assertDispatchedTimes(UserDeactivated::class, 1);
        Event::assertDispatched(UserDeactivated::class, static fn (UserDeactivated $event): bool => $event->userId === $user->id);
        Event::assertNotDispatched(UserRestored::class);

        $user->restore();

        Event::assertDispatchedTimes(UserRestored::class, 1);
        Event::assertDispatched(UserRestored::class, static fn (UserRestored $event): bool => $event->userId === $user->id);
        Event::assertDispatchedTimes(UserDeactivated::class, 1);
    }

    public function test_a_permanent_deletion_and_a_rolled_back_deactivation_dispatch_nothing(): void
    {
        $forced = UserModel::factory()->create();
        $rolledBack = UserModel::factory()->create();
        Event::fake([UserDeactivated::class]);

        $forced->forceDelete();

        try {
            DB::transaction(function () use ($rolledBack): void {
                $rolledBack->delete();

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(UserDeactivated::class);
    }

    public function test_editing_the_profile_from_the_panel_dispatches_the_profile_event_once(): void
    {
        $this->signInAsAdminOnThePanel();
        $this->role('game-master');
        $target = UserModel::factory()->create();
        app(MemberRoleServiceInterface::class)->assignTo($target);
        Event::fake([UserProfileUpdated::class]);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['display_name' => 'New display name'])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertDispatchedTimes(UserProfileUpdated::class, 1);
        Event::assertDispatched(UserProfileUpdated::class, static fn (UserProfileUpdated $event): bool => $event->userId === $target->id);
    }

    public function test_saving_only_roles_or_nothing_from_the_panel_leaves_the_profile_event_out(): void
    {
        $this->signInAsAdminOnThePanel();
        $this->role('game-master');
        $target = UserModel::factory()->create();
        app(MemberRoleServiceInterface::class)->assignTo($target);
        Event::fake([UserProfileUpdated::class]);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => ['member', 'game-master']])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertNotDispatched(UserProfileUpdated::class);
    }

    private function signInAsAdminOnThePanel(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->create(['name' => $name, 'display_name' => ucfirst($name)]);
    }

    private function permission(string $key): PermissionModel
    {
        [$resource, $action] = explode('.', $key, 2);

        return PermissionModel::query()->firstOrCreate(
            ['key' => $key],
            ['label' => $key, 'resource' => $resource, 'action' => $action],
        );
    }
}
