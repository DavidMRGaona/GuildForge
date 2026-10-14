<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

    private function stored(): mixed
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->clearCache();

        return $settings->get(self::KEY);
    }

    private function select(SiteSettings $page): Select
    {
        $select = $page->form->getComponent(fn ($component): bool => $component instanceof Select && $component->getName() === self::KEY, withHidden: true);
        $this->assertInstanceOf(Select::class, $select);

        return $select;
    }
}
