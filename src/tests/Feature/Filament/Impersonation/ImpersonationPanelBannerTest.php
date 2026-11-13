<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Impersonation;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * The panel band and the redirect for an account without panel access (core#578).
 */
final class ImpersonationPanelBannerTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
    }

    public function test_the_panel_shows_whom_the_administrator_is_viewing_and_how_to_go_back(): void
    {
        $editor = $this->editorWithPermissions(['admin.access']);
        $editor->forceFill(['name' => 'Marta Ejemplo', 'display_name' => null])->save();
        $this->startImpersonating($editor, ImpersonationMode::ReadOnly);

        $this->get('/admin')
            ->assertOk()
            ->assertSee(__('filament.impersonation.banner.viewing_as', ['name' => 'Marta Ejemplo']))
            ->assertSee(__('filament.impersonation.banner.read_only'))
            ->assertSee(__('filament.impersonation.banner.stop'))
            ->assertSee('action="'.route('impersonation.stop').'"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_simulate_mode_says_so_in_another_colour(): void
    {
        $editor = $this->editorWithPermissions(['admin.access']);
        $this->startImpersonating($editor, ImpersonationMode::Simulate);

        $this->get('/admin')
            ->assertOk()
            ->assertSee(__('filament.impersonation.banner.simulate'))
            ->assertDontSee(__('filament.impersonation.banner.read_only'))
            ->assertSee('--color-600: var(--warning-600)', false);
    }

    public function test_read_only_mode_does_not_use_the_simulate_colour(): void
    {
        $editor = $this->editorWithPermissions(['admin.access']);
        $this->startImpersonating($editor, ImpersonationMode::ReadOnly);

        $this->get('/admin')
            ->assertSee('--color-600: var(--info-600)', false)
            ->assertDontSee('--color-600: var(--warning-600)', false);
    }

    public function test_there_is_no_band_without_an_impersonation(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin')
            ->assertOk()
            ->assertDontSee(__('filament.impersonation.banner.stop'));
    }

    public function test_an_account_without_panel_access_goes_to_the_site_with_a_notice(): void
    {
        $member = UserModel::factory()->create(['name' => 'Luis Ejemplo', 'display_name' => null]);
        $this->startImpersonating($member);

        $this->get('/admin')
            ->assertRedirect(route('home'))
            ->assertSessionHas('warning', __('filament.impersonation.no_panel_access', ['name' => 'Luis Ejemplo', 'display_name' => null]));
    }

    public function test_the_notice_prefers_the_display_name(): void
    {
        $member = UserModel::factory()->create(['name' => 'Luis Ejemplo', 'display_name' => 'Lu']);
        $this->startImpersonating($member);

        $this->get('/admin')->assertSessionHas('warning', __('filament.impersonation.no_panel_access', ['name' => 'Lu']));
    }

    public function test_the_redirect_keeps_the_impersonation_going(): void
    {
        $member = UserModel::factory()->create();
        $this->startImpersonating($member);

        $this->get('/admin')->assertRedirect(route('home'));
        $this->get('/admin')->assertRedirect(route('home'));
    }

    public function test_the_administrator_without_an_impersonation_is_not_redirected(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin')->assertOk();
    }

    private function startImpersonating(UserModel $target, ImpersonationMode $mode = ImpersonationMode::ReadOnly): void
    {
        $this->actingAs($this->admin);
        app(ImpersonationServiceInterface::class)->start($this->admin, $target, $mode);
    }
}
