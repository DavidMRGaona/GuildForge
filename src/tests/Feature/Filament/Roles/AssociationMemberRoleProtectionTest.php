<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Application\Authorization\DTOs\UpdateRoleDTO;
use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Authorization\Exceptions\RoleProtectedException;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Modules find association members by the role configured in the site settings: deleting
 * or renaming that role would silently strip every member of their benefits.
 */
final class AssociationMemberRoleProtectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_the_policy_refuses_to_delete_the_configured_role(): void
    {
        $socio = $this->role('socio');

        $this->assertFalse($this->admin->can('delete', $socio));
        $this->assertFalse($this->admin->can('forceDelete', $socio));
    }

    public function test_the_policy_follows_the_configured_role(): void
    {
        $socio = $this->role('socio');
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'junta');

        $this->assertFalse($this->admin->can('delete', $junta));
        $this->assertTrue($this->admin->can('delete', $socio));
    }

    public function test_the_configured_role_has_no_delete_action_in_the_table(): void
    {
        $socio = $this->role('socio');

        Livewire::test(ListRoles::class)
            ->assertTableActionHidden(DeleteAction::class, $socio);
    }

    public function test_the_configured_role_has_no_delete_action_in_the_edit_page(): void
    {
        $socio = $this->role('socio');

        Livewire::test(EditRole::class, ['record' => $socio->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_a_forced_delete_from_the_table_keeps_the_configured_role(): void
    {
        $socio = $this->role('socio');

        $this->forceTableDelete($socio);

        $this->assertTrue(RoleModel::query()->whereKey($socio->id)->exists());
    }

    public function test_a_forced_delete_from_the_edit_page_keeps_the_configured_role(): void
    {
        $socio = $this->role('socio');

        $this->forcePageDelete($socio);

        $this->assertTrue(RoleModel::query()->whereKey($socio->id)->exists());
    }

    public function test_the_forced_delete_calls_do_delete_a_role_that_is_not_configured(): void
    {
        // Control for the two tests above: the raw calls they send do reach the delete action
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $consejo = RoleModel::query()->create(['name' => 'consejo', 'display_name' => 'Consejo']);

        $this->forceTableDelete($junta);
        $this->forcePageDelete($consejo);

        $this->assertFalse(RoleModel::query()->whereKey($junta->id)->exists());
        $this->assertFalse(RoleModel::query()->whereKey($consejo->id)->exists());
    }

    public function test_the_configured_role_cannot_be_deleted_through_the_role_service(): void
    {
        $socio = $this->role('socio');

        try {
            app(RoleServiceInterface::class)->delete($socio->id);
            $this->fail('The configured association member role was deleted');
        } catch (RoleProtectedException) {
            $this->assertTrue(RoleModel::query()->whereKey($socio->id)->exists());
        }
    }

    public function test_the_name_of_the_configured_role_is_disabled(): void
    {
        $socio = $this->role('socio');

        Livewire::test(EditRole::class, ['record' => $socio->getRouteKey()])
            ->assertFormFieldIsDisabled('name');
    }

    public function test_a_forged_rename_of_the_configured_role_is_not_saved(): void
    {
        $socio = $this->role('socio');

        Livewire::test(EditRole::class, ['record' => $socio->getRouteKey()])
            ->set('data.name', 'antiguos-socios')
            ->set('data.display_name', 'Socio de pleno derecho')
            ->call('save')
            ->assertHasNoFormErrors();

        $socio->refresh();
        $this->assertSame('socio', $socio->name);
        $this->assertSame('Socio de pleno derecho', $socio->display_name);
    }

    public function test_the_save_guard_drops_the_name_of_the_configured_role_on_its_own(): void
    {
        $socio = $this->role('socio');
        $page = Livewire::test(EditRole::class, ['record' => $socio->getRouteKey()])->instance();

        // Called directly: the guard must hold even if the name field were enabled
        $data = $this->mutateBeforeSave($page, ['name' => 'antiguos-socios', 'display_name' => 'Socios']);

        $this->assertArrayNotHasKey('name', $data);
        $this->assertSame('Socios', $data['display_name']);
    }

    public function test_the_save_guard_keeps_the_name_of_a_role_that_is_not_configured(): void
    {
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $page = Livewire::test(EditRole::class, ['record' => $junta->getRouteKey()])->instance();

        $data = $this->mutateBeforeSave($page, ['name' => 'directiva', 'display_name' => 'Directiva']);

        $this->assertSame(['name' => 'directiva', 'display_name' => 'Directiva'], $data);
    }

    public function test_the_role_service_keeps_the_name_of_the_configured_role(): void
    {
        $socio = $this->role('socio');

        app(RoleServiceInterface::class)->update($socio->id, new UpdateRoleDTO(displayName: 'Socios'));

        $this->assertSame('socio', $socio->refresh()->name);
    }

    public function test_a_role_that_is_not_configured_can_be_renamed(): void
    {
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);

        Livewire::test(EditRole::class, ['record' => $junta->getRouteKey()])
            ->assertFormFieldIsEnabled('name')
            ->fillForm(['name' => 'directiva'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('directiva', $junta->refresh()->name);
    }

    public function test_a_role_that_is_not_configured_can_be_deleted_from_the_table(): void
    {
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);

        Livewire::test(ListRoles::class)
            ->assertTableActionVisible(DeleteAction::class, $junta)
            ->callTableAction(DeleteAction::class, $junta);

        $this->assertFalse(RoleModel::query()->whereKey($junta->id)->exists());
    }

    public function test_a_role_that_is_not_configured_can_be_deleted_from_the_edit_page(): void
    {
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);

        Livewire::test(EditRole::class, ['record' => $junta->getRouteKey()])
            ->assertActionVisible(DeleteAction::class)
            ->callAction(DeleteAction::class);

        $this->assertFalse(RoleModel::query()->whereKey($junta->id)->exists());
    }

    public function test_the_default_role_can_be_deleted_once_another_role_is_configured(): void
    {
        $socio = $this->role('socio');
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'junta');

        Livewire::test(EditRole::class, ['record' => $socio->getRouteKey()])
            ->assertFormFieldIsEnabled('name')
            ->callAction(DeleteAction::class);

        $this->assertFalse(RoleModel::query()->whereKey($socio->id)->exists());
    }

    /**
     * What a forged Livewire request sends, bypassing the hidden action of the test helpers.
     */
    private function forceTableDelete(RoleModel $role): void
    {
        Livewire::test(ListRoles::class)
            ->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => $role->getKey()])
            ->call('callMountedAction');
    }

    private function forcePageDelete(RoleModel $role): void
    {
        Livewire::test(EditRole::class, ['record' => $role->getKey()])
            ->call('mountAction', 'delete')
            ->call('callMountedAction');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mutateBeforeSave(object $page, array $data): array
    {
        $this->assertInstanceOf(EditRole::class, $page);

        /** @var array<string, mixed> */
        return (new ReflectionMethod($page, 'mutateFormDataBeforeSave'))->invoke($page, $data);
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
    }
}
