<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Application\Services\SettingsServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\WebApp\Services\ServiceWorkerScriptInterface;
use App\Infrastructure\Authorization\Services\TwoFactorPanelAccess;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Tests\TestCase;

final class EnsureSiteIsNotInMaintenanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_sees_the_site_when_maintenance_is_disabled(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_guest_gets_the_maintenance_page_when_maintenance_is_enabled(): void
    {
        $this->enableMaintenance('Volvemos en 15 minutos');

        $this->get('/')
            ->assertStatus(503)
            ->assertHeader('Retry-After')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Maintenance')
                ->where('message', 'Volvemos en 15 minutos'));
    }

    public function test_maintenance_page_has_no_message_when_none_is_configured(): void
    {
        $this->enableMaintenance();

        $this->get('/')
            ->assertStatus(503)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Maintenance')
                ->where('message', null));
    }

    public function test_member_without_panel_access_gets_the_maintenance_page(): void
    {
        $this->enableMaintenance();
        $this->actingAs(UserModel::factory()->create());

        $this->get('/')->assertStatus(503);
    }

    public function test_editor_sees_the_site_during_maintenance(): void
    {
        $this->enableMaintenance();
        $this->actingAs(UserModel::factory()->editor()->create());

        $this->get('/')->assertOk();
    }

    public function test_admin_sees_the_site_during_maintenance(): void
    {
        $this->enableMaintenance();
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get('/')->assertOk();
    }

    public function test_custom_role_with_panel_access_sees_the_site_during_maintenance(): void
    {
        $this->enableMaintenance();
        $role = RoleModel::create(['name' => 'treasury', 'display_name' => 'Treasury']);
        $permission = PermissionModel::create([
            'key' => 'admin.access',
            'label' => 'admin.access',
            'resource' => 'admin',
            'action' => 'access',
        ]);
        $role->permissions()->attach($permission->id);
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $this->actingAs($user);

        $this->get('/')->assertOk();
    }

    public function test_admin_panel_login_stays_reachable_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->get('/admin/login')->assertOk();
    }

    public function test_public_login_page_stays_reachable_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->get('/iniciar-sesion')->assertOk();
    }

    public function test_editor_can_log_in_through_the_public_login_during_maintenance(): void
    {
        $this->enableMaintenance();
        $editor = UserModel::factory()->editor()->create();

        $this->post('/iniciar-sesion', ['email' => $editor->email, 'password' => 'password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($editor);
    }

    public function test_editor_with_two_factor_completes_the_challenge_during_maintenance(): void
    {
        $this->enableMaintenance();
        $editor = UserModel::factory()->editor()->create();
        $twoFactor = app(TwoFactorServiceInterface::class);
        $secret = $twoFactor->beginSetup((string) $editor->id)->secret;
        $twoFactor->confirmSetup((string) $editor->id, (new Google2FA)->getCurrentOtp($secret));

        $this->post('/iniciar-sesion', ['email' => $editor->email, 'password' => 'password'])
            ->assertRedirect('/iniciar-sesion/verificacion');

        // The second step is not the maintenance page, before or after the code
        $this->get('/iniciar-sesion/verificacion')->assertOk();
        $this->post('/iniciar-sesion/verificacion', ['code' => (new Google2FA)->oathTotp($secret, intdiv(time(), 30) + 1)])
            ->assertRedirect();

        $this->assertAuthenticatedAs($editor);
    }

    public function test_member_who_logs_in_during_maintenance_still_gets_the_maintenance_page(): void
    {
        $this->enableMaintenance();
        $member = UserModel::factory()->create();

        $this->post('/iniciar-sesion', ['email' => $member->email, 'password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($member);
        $this->get('/')->assertStatus(503);
    }

    public function test_livewire_endpoint_stays_reachable_during_maintenance(): void
    {
        $this->enableMaintenance();

        // The admin login posts here before any session exists. Past the maintenance
        // middleware, Livewire itself answers the empty payload, never with a 503
        $response = $this->withHeaders(['X-Livewire' => 'true'])
            ->postJson(Livewire::getUpdateUri(), ['components' => []]);

        $this->assertNotSame(503, $response->getStatusCode(), Livewire::getUpdateUri().' answered with the maintenance page');
    }

    public function test_health_check_stays_up_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->get('/up')->assertOk();
    }

    public function test_web_app_manifest_and_service_worker_stay_reachable_during_maintenance(): void
    {
        $this->enableMaintenance();
        $this->app->instance(ServiceWorkerScriptInterface::class, new class implements ServiceWorkerScriptInterface
        {
            public function script(): string
            {
                return 'self.addEventListener("push", () => {});';
            }
        });

        // A browser that met a 503 instead would keep a broken service worker
        $this->get('/manifest.webmanifest')->assertOk();
        $this->get('/sw.js')->assertOk();
    }

    public function test_whoever_waits_for_two_factor_to_enter_the_panel_reaches_the_security_tab(): void
    {
        $this->enableMaintenance();
        $user = $this->holderOfPanelRoleRequiringTwoFactor();

        $this->actingAs($user)->get(route('profile.show', ['tab' => 'seguridad']))->assertOk();
        $this->actingAs($user)->get(route('profile.show'))->assertRedirect(route('profile.show', ['tab' => 'seguridad']));
        $this->actingAs($user)->get(route('profile.show', ['tab' => 'cuenta']))->assertRedirect(route('profile.show', ['tab' => 'seguridad']));
        $this->actingAs($user)
            ->postJson(route('profile.two-factor.begin'), ['current_password' => 'password'])
            ->assertOk();
    }

    public function test_whoever_waits_for_two_factor_still_gets_the_maintenance_page_elsewhere(): void
    {
        $this->enableMaintenance();
        $user = $this->holderOfPanelRoleRequiringTwoFactor();

        $this->actingAs($user)->get('/')->assertStatus(503);
        $this->actingAs($user)->postJson(route('profile.update'), [])->assertStatus(503);
        $this->actingAs($user)->putJson(route('profile.password'), [])->assertStatus(503);
    }

    public function test_a_member_without_such_a_role_does_not_reach_the_security_tab(): void
    {
        $this->enableMaintenance();
        $role = RoleModel::query()->create(['name' => 'secretaria', 'display_name' => 'Secretaría', 'requires_two_factor' => true]);
        $member = UserModel::factory()->create();
        // Requires two-factor, but would not open the panel even with it
        $member->roles()->attach($role->id);

        $this->actingAs($member)->get(route('profile.show', ['tab' => 'seguridad']))->assertStatus(503);
        $this->actingAs(UserModel::factory()->create())->get(route('profile.show', ['tab' => 'seguridad']))->assertStatus(503);
        $this->get(route('profile.show', ['tab' => 'seguridad']))->assertStatus(503);
    }

    public function test_the_security_tab_stays_closed_when_the_check_fails(): void
    {
        $this->enableMaintenance();
        $user = $this->holderOfPanelRoleRequiringTwoFactor();
        $this->app->bind(TwoFactorPanelAccess::class, static fn () => throw new RuntimeException('check failed'));

        $this->actingAs($user)->get(route('profile.show', ['tab' => 'seguridad']))->assertStatus(503);
    }

    public function test_the_security_tab_alone_is_sent_during_maintenance(): void
    {
        $this->enableMaintenance();
        $user = $this->holderOfPanelRoleRequiringTwoFactor();

        $this->actingAs($user)->get(route('profile.show', ['tab' => 'seguridad']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Show')
                ->where('securityOnly', true)
                ->has('security.twoFactor')
                ->missing('calendarFeed')
                ->missing('proposals')
                ->missing('mailPreferences'));
    }

    private function holderOfPanelRoleRequiringTwoFactor(): UserModel
    {
        $role = RoleModel::query()->create(['name' => 'tesoreria', 'display_name' => 'Tesorería', 'requires_two_factor' => true]);
        $permission = PermissionModel::query()->firstOrCreate(
            ['key' => 'admin.access'],
            ['label' => 'admin.access', 'resource' => 'admin', 'action' => 'access'],
        );
        $role->permissions()->attach($permission->id);
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function enableMaintenance(string $message = ''): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('maintenance_enabled', '1');
        $settings->set('maintenance_message', $message);
    }
}
