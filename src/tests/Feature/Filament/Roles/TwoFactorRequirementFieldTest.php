<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Events\TwoFactorRolesPaused;
use App\Domain\Events\UserActiveRolesChanged;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\TwoFactorRolesPausedNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\Authorization\ManagesTwoFactorRoles;
use Tests\TestCase;

/**
 * «Requiere doble factor» in the role editor (core#772): where it may go, the brake on one's own
 * role, the history entry, the impact hint and who is told when it is turned on.
 */
final class TwoFactorRequirementFieldTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ManagesTwoFactorRoles;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_requirement_is_saved_and_logged(): void
    {
        $role = $this->role('tesoreria');

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['requires_two_factor' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($role->refresh()->requires_two_factor);

        $entry = RoleAuditEntryModel::query()->where('role_id', $role->id)->where('event', 'role_updated')->latest('id')->firstOrFail();
        $this->assertSame(['from' => false, 'to' => true], $entry->changes['fields']['requires_two_factor'] ?? null);
    }

    public function test_admin_may_require_it_but_the_base_and_association_roles_may_not(): void
    {
        Livewire::test(EditRole::class, ['record' => $this->role('admin')->getRouteKey()])
            ->assertFormFieldIsEnabled('requires_two_factor');

        foreach (['member', 'socio'] as $name) {
            Livewire::test(EditRole::class, ['record' => $this->role($name)->getRouteKey()])
                ->assertFormFieldIsDisabled('requires_two_factor');
        }
    }

    public function test_a_forged_requirement_on_the_association_role_is_dropped(): void
    {
        $socio = $this->role('socio');

        Livewire::test(EditRole::class, ['record' => $socio->getRouteKey()])
            ->set('data.requires_two_factor', true)
            ->call('save');

        $this->assertFalse($socio->refresh()->requires_two_factor);
    }

    public function test_a_new_role_may_be_created_requiring_it(): void
    {
        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'tesoreria', 'display_name' => 'Tesorería', 'requires_two_factor' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(RoleModel::query()->where('name', 'tesoreria')->firstOrFail()->requires_two_factor);
    }

    public function test_nobody_marks_a_role_they_hold_without_two_factor_on(): void
    {
        $role = $this->role('admin');

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['requires_two_factor' => true])
            ->call('save')
            ->assertHasFormErrors(['requires_two_factor']);

        $this->assertFalse($role->refresh()->requires_two_factor);
    }

    public function test_the_brake_lets_through_once_the_actor_has_two_factor_on(): void
    {
        $role = $this->role('admin');
        $this->turnOnTwoFactor($this->admin);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['requires_two_factor' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($role->refresh()->requires_two_factor);
    }

    public function test_the_brake_never_stops_turning_it_off(): void
    {
        $role = $this->role('admin');
        $role->update(['requires_two_factor' => true]);
        $this->turnOnTwoFactor($this->admin);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['requires_two_factor' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($role->refresh()->requires_two_factor);
    }

    public function test_turning_it_on_tells_how_many_holders_pause(): void
    {
        $role = $this->role('tesoreria');
        $this->accountHolding(['tesoreria']);
        $this->accountHolding(['tesoreria']);
        $this->turnOnTwoFactor($this->accountHolding(['tesoreria']));

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['requires_two_factor' => true])
            ->assertSee('2 personas quedarán con este rol en pausa hasta que activen el doble factor.');
    }

    public function test_turning_it_on_tells_the_holders_without_two_factor_and_the_modules(): void
    {
        $role = $this->role('tesoreria');
        $without = $this->accountHolding(['tesoreria']);
        $with = $this->accountHolding(['tesoreria']);
        $this->turnOnTwoFactor($with);
        Notification::fake();
        Event::fake([UserActiveRolesChanged::class]);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['requires_two_factor' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertSentTo($without, TwoFactorRolesPausedNotification::class);
        Notification::assertNotSentTo($with, TwoFactorRolesPausedNotification::class);
        Event::assertDispatched(UserActiveRolesChanged::class, fn (UserActiveRolesChanged $event): bool => $event->userId === $without->id
            && $event->rolesPaused === ['tesoreria'] && $event->rolesActivated === []);
        Event::assertNotDispatched(UserActiveRolesChanged::class, fn (UserActiveRolesChanged $event): bool => $event->userId === $with->id);
    }

    public function test_turning_it_off_activates_the_role_for_holders_without_two_factor(): void
    {
        $role = $this->role('tesoreria');
        $role->update(['requires_two_factor' => true]);
        $without = $this->accountHolding(['tesoreria']);
        Event::fake([UserActiveRolesChanged::class, TwoFactorRolesPaused::class]);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['requires_two_factor' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertDispatched(UserActiveRolesChanged::class, fn (UserActiveRolesChanged $event): bool => $event->userId === $without->id
            && $event->rolesActivated === ['tesoreria']);
        Event::assertNotDispatched(TwoFactorRolesPaused::class);
    }

    public function test_turning_it_on_drops_what_holders_had_cached(): void
    {
        $role = $this->role('tesoreria');
        $this->givePermissions($role, ['events.create']);
        $holder = $this->accountHolding(['tesoreria']);
        $authorization = app(AuthorizationServiceInterface::class);
        $this->assertTrue($authorization->can($holder, 'events.create'));

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['requires_two_factor' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($authorization->can($holder, 'events.create'));
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
    }
}
