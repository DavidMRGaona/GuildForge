<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;
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

    public function test_member_who_logs_in_during_maintenance_still_gets_the_maintenance_page(): void
    {
        $this->enableMaintenance();
        $member = UserModel::factory()->create();

        $this->post('/iniciar-sesion', ['email' => $member->email, 'password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($member);
        $this->get('/')->assertStatus(503);
    }

    public function test_health_check_stays_up_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->get('/up')->assertOk();
    }

    private function enableMaintenance(string $message = ''): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('maintenance_enabled', '1');
        $settings->set('maintenance_message', $message);
    }
}
