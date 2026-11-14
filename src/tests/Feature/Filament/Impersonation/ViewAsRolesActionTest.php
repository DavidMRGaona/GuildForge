<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Impersonation;

use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Infrastructure\Impersonation\ImpersonationSessionState;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * «Ver como roles» in the header of the user and role lists (core#644).
 */
final class ViewAsRolesActionTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_the_action_is_offered_to_an_administrator_on_both_lists(): void
    {
        Livewire::test(ListUsers::class)->assertActionVisible('viewAsRoles');
        Livewire::test(ListRoles::class)->assertActionVisible('viewAsRoles');
    }

    public function test_it_is_not_offered_without_the_permission(): void
    {
        $editor = $this->editorWithPermissions(['admin.access', 'users.view_any', 'roles.view_any']);
        $this->actingAs($editor);

        Livewire::test(ListUsers::class)->assertActionHidden('viewAsRoles');
        Livewire::test(ListRoles::class)->assertActionHidden('viewAsRoles');
    }

    public function test_admin_is_not_among_the_options(): void
    {
        $adminId = $this->roleId('admin');
        $memberId = $this->roleId('member');

        Livewire::test(ListRoles::class)
            ->mountAction('viewAsRoles')
            ->assertSchemaComponentExists('roles', checkComponentUsing: function (CheckboxList $field) use ($adminId, $memberId): bool {
                $options = $field->getOptions();

                return ! array_key_exists($adminId, $options) && array_key_exists($memberId, $options);
            });

    }

    public function test_the_base_role_is_ticked_by_default(): void
    {
        Livewire::test(ListUsers::class)
            ->mountAction('viewAsRoles')
            ->assertSchemaStateSet(['roles' => [$this->roleId('member')], 'simulate' => false]);
    }

    public function test_confirming_starts_the_view_and_goes_to_the_site(): void
    {
        $memberId = $this->roleId('member');
        $socioId = $this->roleId('socio');

        Livewire::test(ListUsers::class)
            ->callAction('viewAsRoles', ['roles' => [$memberId, $socioId], 'simulate' => false])
            ->assertHasNoActionErrors()
            ->assertRedirect(route('home'));

        $state = app(Session::class)->get(ImpersonationSessionState::SESSION_KEY);
        $this->assertIsArray($state);
        $roleIds = $state['role_ids'];
        sort($roleIds);
        $expected = [$memberId, $socioId];
        sort($expected);
        $this->assertSame($expected, $roleIds);
    }

    public function test_a_forged_admin_role_is_rejected(): void
    {
        Livewire::test(ListRoles::class)
            ->callAction('viewAsRoles', ['roles' => [$this->roleId('member'), $this->roleId('admin')], 'simulate' => false])
            ->assertHasActionErrors(['roles.1']);

        $this->assertFalse(app(Session::class)->has(ImpersonationSessionState::SESSION_KEY));
    }

    private function roleId(string $name): string
    {
        return (string) RoleModel::query()->where('name', $name)->value('id');
    }
}
