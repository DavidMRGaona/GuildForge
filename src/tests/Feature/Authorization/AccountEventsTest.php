<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Events\RoleDeleted;
use App\Domain\Events\RolePermissionsChanged;
use App\Domain\Events\UserActiveRolesChanged;
use App\Domain\Events\UserAnonymized;
use App\Domain\Events\UserDeactivated;
use App\Domain\Events\UserProfileUpdated;
use App\Domain\Events\UserRestored;
use App\Domain\Events\UserRolesChanged;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Authorization\Services\RoleChangeAnnouncer;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteAction;
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

    public function test_deleting_a_role_dispatches_one_event_with_its_holders_and_no_roles_event(): void
    {
        $role = $this->role('moderator');
        $role->permissions()->attach([$this->permission('events.create')->id, $this->permission('events.view_any')->id]);
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();
        $this->authorization->assignRole($first, 'moderator');
        $this->authorization->assignRole($second, 'moderator');
        Event::fake([RoleDeleted::class, UserRolesChanged::class]);

        app(RoleServiceInterface::class)->delete($role->id);

        Event::assertDispatchedTimes(RoleDeleted::class, 1);
        Event::assertDispatched(RoleDeleted::class, static function (RoleDeleted $event) use ($role, $first, $second): bool {
            $ids = [$first->id, $second->id];
            sort($ids);

            return $event->roleId === $role->id
                && $event->roleName === 'moderator'
                && $event->permissions === ['events.create', 'events.view_any']
                && $event->holderIds === $ids;
        });
        Event::assertNotDispatched(UserRolesChanged::class);
    }

    public function test_a_rolled_back_role_deletion_dispatches_nothing(): void
    {
        $role = $this->role('moderator');
        Event::fake([RoleDeleted::class]);

        try {
            DB::transaction(function () use ($role): void {
                app(RoleServiceInterface::class)->delete($role->id);

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(RoleDeleted::class);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_deleting_a_role_from_the_panel_dispatches_one_event(): void
    {
        $this->signInAsAdminOnThePanel();
        $role = $this->role('moderator');
        $holder = UserModel::factory()->create();
        $this->authorization->assignRole($holder, 'moderator');
        Event::fake([RoleDeleted::class]);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->callAction(DeleteAction::class);

        Event::assertDispatchedTimes(RoleDeleted::class, 1);
        Event::assertDispatched(
            RoleDeleted::class,
            static fn (RoleDeleted $event): bool => $event->roleName === 'moderator' && $event->holderIds === [$holder->id],
        );
    }

    public function test_switching_the_association_role_announces_the_accounts_whose_roles_turn_on_or_off(): void
    {
        $this->signInAsAdminOnThePanel();
        app(SettingsServiceInterface::class)->set('association_member_role', 'socio');
        $this->role('socio');
        $this->role('junta');
        $this->role('tutor')->update(['requires_association_member' => true]);
        $this->role('other')->update(['requires_association_member' => true]);
        $paused = UserModel::factory()->create();
        $activated = UserModel::factory()->create();
        $both = UserModel::factory()->create();
        $plain = UserModel::factory()->create();
        $this->authorization->assignRole($paused, 'socio');
        $this->authorization->assignRole($paused, 'tutor');
        $this->authorization->assignRole($paused, 'other');
        $this->authorization->assignRole($activated, 'junta');
        $this->authorization->assignRole($activated, 'tutor');
        $this->authorization->assignRole($both, 'socio');
        $this->authorization->assignRole($both, 'junta');
        $this->authorization->assignRole($both, 'tutor');
        $this->authorization->assignRole($plain, 'socio');
        Event::fake([UserActiveRolesChanged::class, UserRolesChanged::class]);

        Livewire::test(SiteSettings::class)
            ->fillForm(['association_member_role' => 'junta'])
            ->call('save');

        Event::assertDispatchedTimes(UserActiveRolesChanged::class, 2);
        Event::assertDispatched(
            UserActiveRolesChanged::class,
            static fn (UserActiveRolesChanged $event): bool => $event->userId === $paused->id
                && $event->rolesPaused === ['other', 'tutor']
                && $event->rolesActivated === [],
        );
        Event::assertDispatched(
            UserActiveRolesChanged::class,
            static fn (UserActiveRolesChanged $event): bool => $event->userId === $activated->id
                && $event->rolesActivated === ['tutor']
                && $event->rolesPaused === [],
        );
        Event::assertNotDispatched(UserRolesChanged::class);
    }

    public function test_saving_the_site_settings_without_changing_the_association_role_dispatches_nothing(): void
    {
        $this->signInAsAdminOnThePanel();
        app(SettingsServiceInterface::class)->set('association_member_role', 'socio');
        $this->role('socio');
        $this->role('tutor')->update(['requires_association_member' => true]);
        $holder = UserModel::factory()->create();
        $this->authorization->assignRole($holder, 'tutor');
        Event::fake([UserActiveRolesChanged::class]);

        Livewire::test(SiteSettings::class)
            ->fillForm(['association_member_role' => 'socio', 'maintenance_message' => 'Volvemos pronto'])
            ->call('save');

        Event::assertNotDispatched(UserActiveRolesChanged::class);
    }

    public function test_a_rolled_back_association_role_switch_dispatches_nothing(): void
    {
        $this->role('socio');
        $this->role('junta');
        $this->role('tutor')->update(['requires_association_member' => true]);
        $holder = UserModel::factory()->create();
        $this->authorization->assignRole($holder, 'socio');
        $this->authorization->assignRole($holder, 'tutor');
        Event::fake([UserActiveRolesChanged::class]);

        try {
            DB::transaction(function (): void {
                app(RoleChangeAnnouncer::class)->announceAssociationRoleSwitch('socio', 'junta');

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(UserActiveRolesChanged::class);
    }

    public function test_requiring_membership_pauses_the_role_for_non_members_and_dropping_it_activates_it(): void
    {
        $this->signInAsAdminOnThePanel();
        app(SettingsServiceInterface::class)->set('association_member_role', 'socio');
        $this->role('socio');
        $tutor = $this->role('tutor');
        $member = UserModel::factory()->create();
        $outsider = UserModel::factory()->create();
        $this->authorization->assignRole($member, 'socio');
        $this->authorization->assignRole($member, 'tutor');
        $this->authorization->assignRole($outsider, 'tutor');
        Event::fake([UserActiveRolesChanged::class]);

        Livewire::test(EditRole::class, ['record' => $tutor->getRouteKey()])
            ->fillForm(['requires_association_member' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertDispatchedTimes(UserActiveRolesChanged::class, 1);
        Event::assertDispatched(
            UserActiveRolesChanged::class,
            static fn (UserActiveRolesChanged $event): bool => $event->userId === $outsider->id
                && $event->rolesPaused === ['tutor']
                && $event->rolesActivated === [],
        );

        Livewire::test(EditRole::class, ['record' => $tutor->getRouteKey()])
            ->fillForm(['requires_association_member' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertDispatchedTimes(UserActiveRolesChanged::class, 2);
        Event::assertDispatched(
            UserActiveRolesChanged::class,
            static fn (UserActiveRolesChanged $event): bool => $event->userId === $outsider->id
                && $event->rolesActivated === ['tutor']
                && $event->rolesPaused === [],
        );
    }

    public function test_saving_a_role_without_changing_its_requirement_dispatches_no_active_roles_event(): void
    {
        $this->signInAsAdminOnThePanel();
        $tutor = $this->role('tutor');
        $this->authorization->assignRole(UserModel::factory()->create(), 'tutor');
        Event::fake([UserActiveRolesChanged::class]);

        Livewire::test(EditRole::class, ['record' => $tutor->getRouteKey()])
            ->fillForm(['display_name' => 'Tutor nuevo'])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertNotDispatched(UserActiveRolesChanged::class);
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
        return RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
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
