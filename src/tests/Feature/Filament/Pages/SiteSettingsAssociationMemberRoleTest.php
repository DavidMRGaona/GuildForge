<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * The association member role ("socio" by default) is chosen in the site settings; the base
 * roles (admin, editor, member) can never be chosen, not even with a forged Livewire state.
 */
final class SiteSettingsAssociationMemberRoleTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private const string KEY = 'association_member_role';

    public function test_the_options_are_the_existing_roles_except_the_base_ones(): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'editor'], ['display_name' => 'Editor']);
        RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $this->actingAs(UserModel::factory()->admin()->create());

        $options = $this->select(Livewire::test(SiteSettings::class)->instance())->getOptions();

        $this->assertSame(['junta' => 'Junta directiva', 'socio' => 'Socio'], $options);
    }

    public function test_the_field_shows_the_default_role_when_nothing_is_configured(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->assertFormFieldIsVisible(self::KEY)
            ->assertFormSet([self::KEY => 'socio']);
    }

    public function test_saving_persists_the_chosen_role(): void
    {
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm([self::KEY => 'junta'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('junta', $this->stored());
        $this->assertSame('junta', app(AssociationMembershipServiceInterface::class)->roleName());
    }

    public function test_saving_another_field_keeps_the_configured_role(): void
    {
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        app(SettingsServiceInterface::class)->set(self::KEY, 'junta');
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['guild_name' => 'Gremio'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('junta', $this->stored());
    }

    #[DataProvider('forgedValues')]
    public function test_a_forged_value_is_rejected_and_not_saved(string $value): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        app(SettingsServiceInterface::class)->set(self::KEY, 'socio');
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->set('data.'.self::KEY, $value)
            ->call('save')
            ->assertHasFormErrors([self::KEY]);

        $this->assertSame('socio', $this->stored());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forgedValues(): array
    {
        return [
            'admin' => ['admin'],
            'member' => ['member'],
            'missing role' => ['inexistente'],
        ];
    }

    public function test_a_user_with_only_the_maintenance_permission_cannot_change_it(): void
    {
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        app(SettingsServiceInterface::class)->set(self::KEY, 'socio');
        $this->actingAs($this->editorWithPermissions(['settings.maintenance']));

        Livewire::test(SiteSettings::class)
            ->assertFormFieldIsHidden(self::KEY)
            ->set('data.'.self::KEY, 'junta')
            ->set('data.maintenance_enabled', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('socio', $this->stored());
    }

    public function test_a_user_with_the_manage_permission_can_change_it(): void
    {
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $this->actingAs($this->editorWithPermissions(['settings.manage']));

        Livewire::test(SiteSettings::class)
            ->fillForm([self::KEY => 'junta'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('junta', $this->stored());
    }

    #[DataProvider('unusableStoredValues')]
    public function test_an_unusable_stored_role_leaves_the_field_empty_with_a_warning(string $value): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        app(SettingsServiceInterface::class)->set(self::KEY, $value);
        $this->actingAs(UserModel::factory()->admin()->create());

        $component = Livewire::test(SiteSettings::class)
            ->assertOk()
            ->assertFormSet([self::KEY => null]);

        $this->assertNotNull($this->select($component->instance())->getHint());

        // An empty choice cannot be saved: the user has to pick a valid role
        $component->call('save')->assertHasFormErrors([self::KEY => 'required']);
        $this->assertSame($value, $this->stored());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableStoredValues(): array
    {
        return [
            'missing role' => ['junta'],
            'base role' => ['member'],
        ];
    }

    public function test_a_valid_stored_role_shows_no_warning(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $component = Livewire::test(SiteSettings::class);

        $this->assertNull($this->select($component->instance())->getHint());
    }

    public function test_roles_with_panel_access_requirement_or_limit_are_not_offered(): void
    {
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $this->roleWithPanelAccess('panel');
        RoleModel::query()->create(['name' => 'vocal', 'display_name' => 'Vocal', 'requires_association_member' => true]);
        RoleModel::query()->create(['name' => 'tesorero', 'display_name' => 'Tesorero', 'max_holders' => 1]);
        $this->actingAs(UserModel::factory()->admin()->create());

        $options = $this->select(Livewire::test(SiteSettings::class)->instance())->getOptions();

        $this->assertSame(['junta' => 'Junta directiva', 'socio' => 'Socio'], $options);
    }

    #[DataProvider('forgedRestrictedRoles')]
    public function test_forged_panel_role_is_rejected_on_save(string $name): void
    {
        $this->roleWithPanelAccess('panel');
        RoleModel::query()->create(['name' => 'vocal', 'display_name' => 'Vocal', 'requires_association_member' => true]);
        RoleModel::query()->create(['name' => 'tesorero', 'display_name' => 'Tesorero', 'max_holders' => 1]);
        app(SettingsServiceInterface::class)->set(self::KEY, 'socio');
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->set('data.'.self::KEY, $name)
            ->call('save')
            ->assertHasFormErrors([self::KEY]);

        $this->assertSame('socio', $this->stored());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forgedRestrictedRoles(): array
    {
        return [
            'panel access' => ['panel'],
            'requires membership' => ['vocal'],
            'holder cap' => ['tesorero'],
        ];
    }

    public function test_choosing_another_association_role_warns_how_many_roles_go_inactive(): void
    {
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $vocal = RoleModel::query()->create(['name' => 'vocal', 'display_name' => 'Vocal', 'requires_association_member' => true]);
        $socio = RoleModel::query()->where('name', 'socio')->firstOrFail();
        UserModel::factory()->create()->roles()->attach([$vocal->id, $socio->id]);
        UserModel::factory()->create()->roles()->attach([$vocal->id, $socio->id]);
        UserModel::factory()->create()->roles()->attach([$vocal->id, $junta->id]);
        $this->actingAs(UserModel::factory()->admin()->create());

        $component = Livewire::test(SiteSettings::class);
        $this->assertNull($this->select($component->instance())->getHint());

        $component->set('data.'.self::KEY, 'junta');

        $this->assertSame('2 personas quedarán con roles inactivos.', $this->select($component->instance())->getHint());
    }

    public function test_choosing_a_role_that_leaves_nobody_inactive_shows_no_hint(): void
    {
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $vocal = RoleModel::query()->create(['name' => 'vocal', 'display_name' => 'Vocal', 'requires_association_member' => true]);
        $junta = RoleModel::query()->where('name', 'junta')->firstOrFail();
        UserModel::factory()->create()->roles()->attach([$vocal->id, $junta->id]);
        $this->actingAs(UserModel::factory()->admin()->create());

        $component = Livewire::test(SiteSettings::class)->set('data.'.self::KEY, 'junta');

        $select = $this->select($component->instance());
        $this->assertNull($select->getHint());
        $this->assertNull($select->getHintIcon());
    }

    public function test_a_non_admin_cannot_pick_a_role_whose_holders_hold_roles_beyond_them(): void
    {
        $this->associationRoleScenario();
        $actor = $this->editorWithPermissions(['settings.manage', 'users.assign_roles', 'events.view_any']);
        $this->actingAs($actor);

        Livewire::test(SiteSettings::class)
            ->fillForm([self::KEY => 'junta', 'guild_name' => 'Gremio nuevo'])
            ->call('save')
            ->assertNotified(
                Notification::make()
                    ->danger()
                    ->title('No puedes elegir este rol de socio')
                    ->body('Quien lo tiene también tiene roles que requieren ser socio y que tú no puedes dar: Junta de gobierno.'),
            );

        $this->assertSame('socio', $this->stored());
        $this->assertNotSame('Gremio nuevo', app(SettingsServiceInterface::class)->get('guild_name'));
    }

    public function test_a_non_admin_can_pick_it_when_every_role_that_wakes_up_is_grantable(): void
    {
        $this->associationRoleScenario(withBoardHolder: false);
        $actor = $this->editorWithPermissions(['settings.manage', 'users.assign_roles', 'events.view_any']);
        $this->actingAs($actor);

        Livewire::test(SiteSettings::class)
            ->fillForm([self::KEY => 'junta'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('junta', $this->stored());
    }

    public function test_an_admin_can_pick_it_whatever_its_holders_hold(): void
    {
        $this->associationRoleScenario();
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm([self::KEY => 'junta'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('junta', $this->stored());
    }

    /**
     * Holders of "junta" also hold "vocal" (grantable by the actor) and, unless left out,
     * "board" (beyond the actor); both require association membership. Deactivated and
     * anonymized holders of "board" never count.
     */
    private function associationRoleScenario(bool $withBoardHolder = true): void
    {
        app(SettingsServiceInterface::class)->set(self::KEY, 'socio');
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $vocal = $this->roleWith('vocal', 'Vocal', 'events.view_any');
        $board = $this->roleWith('board', 'Junta de gobierno', 'roles.delete');

        UserModel::factory()->create()->roles()->attach([$junta->id, $vocal->id]);
        UserModel::factory()->create(['anonymized_at' => now()])->roles()->attach([$junta->id, $board->id]);
        $deleted = UserModel::factory()->create();
        $deleted->roles()->attach([$junta->id, $board->id]);
        $deleted->delete();

        if ($withBoardHolder) {
            UserModel::factory()->create()->roles()->attach([$junta->id, $board->id]);
        }
    }

    private function roleWith(string $name, string $displayName, string $permissionKey): RoleModel
    {
        $role = RoleModel::query()->create(['name' => $name, 'display_name' => $displayName, 'requires_association_member' => true]);
        [$resource, $action] = explode('.', $permissionKey, 2);
        $permission = PermissionModel::query()->firstOrCreate(
            ['key' => $permissionKey],
            ['label' => $permissionKey, 'resource' => $resource, 'action' => $action],
        );
        $role->permissions()->attach($permission->id);

        return $role;
    }

    public function test_a_saved_role_that_now_has_panel_access_shows_the_warning_instead_of_failing(): void
    {
        $this->roleWithPanelAccess('socio-panel');
        app(SettingsServiceInterface::class)->set(self::KEY, 'socio-panel');
        $this->actingAs(UserModel::factory()->admin()->create());

        $component = Livewire::test(SiteSettings::class)->assertOk();

        $this->assertNotNull($this->select($component->instance())->getHint());
    }

    private function roleWithPanelAccess(string $name): RoleModel
    {
        $role = RoleModel::query()->create(['name' => $name, 'display_name' => ucfirst($name)]);
        $permission = PermissionModel::query()->firstOrCreate(
            ['key' => 'admin.access'],
            ['label' => 'admin.access', 'resource' => 'admin', 'action' => 'access'],
        );
        $role->permissions()->attach($permission->id);

        return $role;
    }

    private function stored(): mixed
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->clearCache();

        return $settings->get(self::KEY);
    }

    private function select(Component $page): Select
    {
        $this->assertInstanceOf(SiteSettings::class, $page);
        $select = $page->form->getComponent(fn ($component): bool => $component instanceof Select && $component->getName() === self::KEY, withHidden: true);
        $this->assertInstanceOf(Select::class, $select);

        return $select;
    }
}
