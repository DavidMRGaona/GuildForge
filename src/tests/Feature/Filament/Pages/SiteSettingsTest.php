<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class SiteSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_member_cannot_access_site_settings(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->get(SiteSettings::getUrl())->assertForbidden();
    }

    public function test_editor_without_settings_permissions_cannot_access_site_settings(): void
    {
        $this->actingAs(UserModel::factory()->editor()->create());

        $this->get(SiteSettings::getUrl())->assertForbidden();
    }

    public function test_admin_can_access_site_settings(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(SiteSettings::getUrl())->assertOk();
    }

    public function test_user_with_maintenance_permission_can_access_site_settings(): void
    {
        $this->actingAs($this->editorWithPermissions(['settings.maintenance']));

        $this->get(SiteSettings::getUrl())->assertOk();
    }

    public function test_user_with_manage_permission_can_access_site_settings(): void
    {
        $this->actingAs($this->editorWithPermissions(['settings.manage']));

        $this->get(SiteSettings::getUrl())->assertOk();
    }

    public function test_admin_sees_general_and_maintenance_fields(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->assertFormFieldIsVisible('guild_name')
            ->assertFormFieldIsVisible('maintenance_enabled')
            ->assertFormFieldIsVisible('maintenance_message');
    }

    public function test_user_with_only_maintenance_permission_sees_only_maintenance_fields(): void
    {
        $this->actingAs($this->editorWithPermissions(['settings.maintenance']));

        Livewire::test(SiteSettings::class)
            ->assertFormFieldIsVisible('maintenance_enabled')
            ->assertFormFieldIsVisible('maintenance_message')
            ->assertFormFieldIsHidden('guild_name')
            ->assertFormFieldIsHidden('auth_registration_enabled');
    }

    public function test_user_with_only_manage_permission_does_not_see_maintenance_fields(): void
    {
        $this->actingAs($this->editorWithPermissions(['settings.manage']));

        Livewire::test(SiteSettings::class)
            ->assertFormFieldIsVisible('guild_name')
            ->assertFormFieldIsHidden('maintenance_enabled')
            ->assertFormFieldIsHidden('maintenance_message');
    }

    public function test_admin_can_enable_maintenance_mode_with_a_message(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'maintenance_enabled' => true,
                'maintenance_message' => 'Volvemos en 15 minutos',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsServiceInterface::class);
        $this->assertTrue($settings->isMaintenanceModeEnabled());
        $this->assertSame('Volvemos en 15 minutos', $settings->get('maintenance_message'));
    }

    public function test_saving_with_only_maintenance_permission_keeps_other_settings_and_images(): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('logos/light.png', 'logo');

        $settings = app(SettingsServiceInterface::class);
        $settings->set('guild_name', 'Gremio de prueba');
        $settings->set('site_logo_light', 'logos/light.png');
        $settings->set('auth_registration_enabled', '0');

        $this->actingAs($this->editorWithPermissions(['settings.maintenance']));

        Livewire::test(SiteSettings::class)
            ->fillForm(['maintenance_enabled' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings->clearCache();
        $this->assertTrue($settings->isMaintenanceModeEnabled());
        $this->assertSame('Gremio de prueba', $settings->get('guild_name'));
        $this->assertSame('logos/light.png', $settings->get('site_logo_light'));
        $this->assertSame('0', $settings->get('auth_registration_enabled'));
        Storage::disk('images')->assertExists('logos/light.png');
    }

    public function test_saving_with_only_manage_permission_does_not_change_maintenance_mode(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('maintenance_enabled', '1');
        $settings->set('maintenance_message', 'Mensaje existente');

        $this->actingAs($this->editorWithPermissions(['settings.manage']));

        Livewire::test(SiteSettings::class)
            ->fillForm(['guild_name' => 'Nuevo nombre'])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings->clearCache();
        $this->assertSame('Nuevo nombre', $settings->get('guild_name'));
        $this->assertTrue($settings->isMaintenanceModeEnabled());
        $this->assertSame('Mensaje existente', $settings->get('maintenance_message'));
    }

    /**
     * Panel access is granted by the editor role; the extra role carries the permissions under test.
     *
     * @param  array<string>  $permissionKeys
     */
    private function editorWithPermissions(array $permissionKeys): UserModel
    {
        $role = RoleModel::create([
            'name' => 'settings_tester',
            'display_name' => 'Settings tester',
        ]);

        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::create([
                'key' => $key,
                'label' => $key,
                'resource' => $resource,
                'action' => $action,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
