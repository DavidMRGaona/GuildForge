<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\RoleRequirementImpact;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class RoleRequirementFieldsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_requirement_and_limit_are_saved(): void
    {
        $role = $this->role('junta');

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['requires_association_member' => true, 'max_holders' => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        $role->refresh();
        $this->assertTrue($role->requires_association_member);
        $this->assertSame(3, $role->max_holders);
    }

    public function test_an_empty_limit_is_saved_as_null_and_zero_is_rejected(): void
    {
        $role = $this->role('junta');
        $role->update(['max_holders' => 2]);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['max_holders' => 0])
            ->call('save')
            ->assertHasFormErrors(['max_holders']);
        $this->assertSame(2, $role->refresh()->max_holders);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['max_holders' => ''])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($role->refresh()->max_holders);
    }

    public function test_requirement_fields_are_disabled_on_admin_member_and_association_role(): void
    {
        foreach (['admin', 'member', 'socio'] as $name) {
            $role = $this->role($name);

            Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
                ->assertFormFieldIsDisabled('requires_association_member')
                ->assertFormFieldIsDisabled('max_holders');
        }

        $junta = $this->role('junta');
        Livewire::test(EditRole::class, ['record' => $junta->getRouteKey()])
            ->assertFormFieldIsEnabled('requires_association_member')
            ->assertFormFieldIsEnabled('max_holders');
    }

    public function test_forged_requirement_on_the_association_role_is_dropped(): void
    {
        $socio = $this->role('socio');

        Livewire::test(EditRole::class, ['record' => $socio->getRouteKey()])
            ->set('data.requires_association_member', true)
            ->set('data.max_holders', 1)
            ->call('save');

        $socio->refresh();
        $this->assertFalse($socio->requires_association_member);
        $this->assertNull($socio->max_holders);
    }

    public function test_a_role_can_be_created_with_the_fields(): void
    {
        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'junta',
                'display_name' => 'Junta',
                'requires_association_member' => true,
                'max_holders' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = RoleModel::query()->where('name', 'junta')->firstOrFail();
        $this->assertTrue($role->requires_association_member);
        $this->assertSame(1, $role->max_holders);
    }

    public function test_impact_counts_holders_who_are_not_association_members(): void
    {
        $junta = $this->role('junta');
        $socio = $this->role('socio');

        $member = UserModel::factory()->create();
        $member->roles()->attach([$junta->id, $socio->id]);
        $outsider = UserModel::factory()->create();
        $outsider->roles()->attach($junta->id);
        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);
        $anonymized->roles()->attach($junta->id);
        $deleted = UserModel::factory()->create();
        $deleted->roles()->attach($junta->id);
        $deleted->delete();

        $this->assertSame(1, app(RoleRequirementImpact::class)->inactiveHolderCount($junta));
    }

    public function test_turning_the_requirement_on_tells_how_many_holders_go_inactive(): void
    {
        $junta = $this->role('junta');
        $this->role('socio');
        UserModel::factory()->create()->roles()->attach($junta->id);

        Livewire::test(EditRole::class, ['record' => $junta->getRouteKey()])
            ->set('data.requires_association_member', true)
            ->assertSee('1 persona quedará con este rol inactivo hasta que sea socia.')
            ->assertDontSee('1 personas');

        UserModel::factory()->create()->roles()->attach($junta->id);

        Livewire::test(EditRole::class, ['record' => $junta->getRouteKey()])
            ->set('data.requires_association_member', true)
            ->assertSee('2 personas quedarán con este rol inactivo hasta que sean socias.');
    }

    public function test_a_limit_below_the_current_holders_warns_and_removes_nobody(): void
    {
        $junta = $this->role('junta');
        $junta->update(['requires_association_member' => true]);
        $holders = UserModel::factory()->count(3)->create();
        foreach ($holders as $holder) {
            // Not association members: the role is inactive for them, yet they still count
            $holder->roles()->attach($junta->id);
        }
        UserModel::factory()->create(['anonymized_at' => now()])->roles()->attach($junta->id);
        $deleted = UserModel::factory()->create();
        $deleted->roles()->attach($junta->id);
        $deleted->delete();
        $warning = 'Ahora lo tienen 3 personas: nadie lo pierde, pero no se podrá dar a nadie más hasta que lo tengan menos de 2.';

        Livewire::test(EditRole::class, ['record' => $junta->getRouteKey()])
            ->assertDontSee('Ahora lo tienen')
            ->set('data.max_holders', 3)
            ->assertDontSee('Ahora lo tienen')
            ->set('data.max_holders', 2)
            ->assertSee($warning)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, $junta->refresh()->max_holders);
        foreach ($holders as $holder) {
            $this->assertTrue($holder->roles()->whereKey($junta->id)->exists());
        }
        $this->assertSame(5, DB::table('user_role')->where('role_id', $junta->id)->count());
    }

    public function test_a_single_place_role_names_its_holder_by_display_name(): void
    {
        $junta = $this->role('junta');
        $junta->update(['max_holders' => 1]);
        UserModel::factory()->create(['name' => 'jroe', 'display_name' => 'Jane Roe'])->roles()->attach($junta->id);

        Livewire::test(EditRole::class, ['record' => $junta->getRouteKey()])
            ->assertSee('Ahora lo tiene Jane Roe.');
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
    }
}
