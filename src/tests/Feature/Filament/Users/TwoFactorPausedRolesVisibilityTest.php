<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Widgets\InactiveRolesWidget;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Pages\Page;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Authorization\ManagesTwoFactorRoles;
use Tests\TestCase;

/**
 * Roles paused for want of two-factor authentication (core#772) are shown with their reason
 * in the panel: the role list of the form, the «Sin doble factor» badge, the filter by reason
 * and the dashboard widget.
 */
final class TwoFactorPausedRolesVisibilityTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;
    use ManagesTwoFactorRoles;

    private const array PERMISSIONS = [
        'users.view_any',
        'users.view',
        'users.update',
        'users.assign_roles',
        'events.create',
    ];

    private RoleModel $treasury;

    protected function setUp(): void
    {
        parent::setUp();

        $this->treasury = $this->roleRequiringTwoFactor('treasury', ['events.create']);
    }

    public function test_the_form_marks_the_role_and_shows_the_badge_while_two_factor_is_off(): void
    {
        $this->actingAs($this->editorWithPermissions(self::PERMISSIONS));
        $holder = $this->accountHolding(['treasury']);

        $page = Livewire::test(EditUser::class, ['record' => $holder->getRouteKey()]);

        $this->assertSame('Requiere doble factor · En pausa: sin doble factor', $this->rolesField($page)->getDescription('treasury'));
        $page->assertSee('Sin doble factor');
    }

    public function test_the_form_shows_no_pause_once_two_factor_is_on(): void
    {
        $this->actingAs($this->editorWithPermissions(self::PERMISSIONS));
        $holder = $this->accountHolding(['treasury']);
        $this->turnOnTwoFactor($holder);

        $field = $this->rolesField(Livewire::test(EditUser::class, ['record' => $holder->getRouteKey()]));

        $this->assertSame('Requiere doble factor', $field->getDescription('treasury'));
    }

    public function test_the_filter_lists_accounts_by_reason(): void
    {
        $this->actingAs($this->editorWithPermissions(self::PERMISSIONS));
        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $board = RoleModel::query()->create(['name' => 'board', 'display_name' => 'Board', 'requires_association_member' => true]);
        $withoutTwoFactor = $this->accountHolding(['treasury']);
        $withTwoFactor = $this->accountHolding(['treasury']);
        $this->turnOnTwoFactor($withTwoFactor);
        $notMember = $this->accountHolding([$board->name]);

        Livewire::test(ListUsers::class)
            ->filterTable('inactive_roles', 'two_factor')
            ->assertCanSeeTableRecords([$withoutTwoFactor])
            ->assertCanNotSeeTableRecords([$withTwoFactor, $notMember]);

        Livewire::test(ListUsers::class)
            ->filterTable('inactive_roles', 'membership')
            ->assertCanSeeTableRecords([$notMember])
            ->assertCanNotSeeTableRecords([$withoutTwoFactor, $withTwoFactor]);

        Livewire::test(ListUsers::class)
            ->filterTable('inactive_roles', 'any')
            ->assertCanSeeTableRecords([$withoutTwoFactor, $notMember])
            ->assertCanNotSeeTableRecords([$withTwoFactor]);
    }

    public function test_the_widget_shows_the_reason_to_who_could_grant_the_role(): void
    {
        $holder = $this->accountHolding(['treasury']);
        $this->actingAs($this->editorWithPermissions(self::PERMISSIONS));

        $this->assertTrue(InactiveRolesWidget::canView());
        Livewire::test(InactiveRolesWidget::class)
            ->assertCanSeeTableRecords([$holder])
            ->assertSee('Treasury: sin doble factor');
    }

    public function test_the_widget_hides_once_two_factor_is_on(): void
    {
        $holder = $this->accountHolding(['treasury']);
        $this->turnOnTwoFactor($holder);
        $this->actingAs($this->editorWithPermissions(self::PERMISSIONS));

        $this->assertFalse(InactiveRolesWidget::canView());
    }

    public function test_the_widget_leaves_out_a_role_the_viewer_cannot_grant(): void
    {
        $this->givePermissions($this->treasury, ['events.unheld_permission']);
        $this->accountHolding(['treasury']);
        $this->actingAs($this->editorWithPermissions(self::PERMISSIONS));

        $this->assertFalse(InactiveRolesWidget::canView());
    }

    /**
     * @param  Testable<Component>  $page
     */
    private function rolesField(Testable $page): CheckboxList
    {
        $instance = $page->instance();
        $this->assertInstanceOf(Page::class, $instance);
        $field = $instance->getSchema('form')?->getFlatFields(withHidden: true)['roles'] ?? null;
        $this->assertInstanceOf(CheckboxList::class, $field);

        return $field;
    }
}
