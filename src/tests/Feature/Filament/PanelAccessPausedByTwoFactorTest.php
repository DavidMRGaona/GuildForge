<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Auth\PanelLogin;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Authorization\ManagesTwoFactorRoles;
use Tests\TestCase;

/**
 * Someone kept out of the panel only because a role that requires two-factor authentication is
 * paused (core#772) is told so, with a link to «Seguridad», at the panel's login and when they
 * open the panel already signed in. Nobody else sees that message.
 */
final class PanelAccessPausedByTwoFactorTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ManagesTwoFactorRoles;

    private const string PASSWORD = 'correct horse battery staple';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->roleRequiringTwoFactor('tesoreria', ['admin.access', 'events.create']);
    }

    public function test_the_login_says_the_role_requires_two_factor(): void
    {
        $user = $this->holderWithPassword(['tesoreria']);

        $this->signIn($user, self::PASSWORD)
            ->assertHasErrors(['data.email'])
            ->assertSee(__('two_factor.roles.panel_paused'))
            ->assertNotified(__('two_factor.roles.panel_paused'));

        $this->assertGuest();
    }

    public function test_an_administrator_whose_role_requires_it_is_told_too(): void
    {
        $admin = UserModel::factory()->admin()->create(['password' => self::PASSWORD]);
        RoleModel::query()->where('name', 'admin')->update(['requires_two_factor' => true]);

        $this->signIn($admin, self::PASSWORD)->assertSee(__('two_factor.roles.panel_paused'));

        $this->assertGuest();
    }

    public function test_a_wrong_password_never_reveals_the_paused_role(): void
    {
        $user = $this->holderWithPassword(['tesoreria']);

        $this->signIn($user, 'wrong password')
            ->assertHasErrors(['data.email'])
            ->assertDontSee(__('two_factor.roles.panel_paused'))
            ->assertNotNotified(__('two_factor.roles.panel_paused'));
    }

    public function test_an_account_without_panel_access_gets_the_usual_refusal(): void
    {
        $member = UserModel::factory()->create(['password' => self::PASSWORD]);

        $this->signIn($member, self::PASSWORD)
            ->assertHasErrors(['data.email'])
            ->assertDontSee(__('two_factor.roles.panel_paused'));
    }

    public function test_a_role_that_would_not_open_the_panel_gets_the_usual_refusal(): void
    {
        $this->roleRequiringTwoFactor('secretaria', ['events.create']);
        $user = $this->holderWithPassword(['secretaria']);

        $this->signIn($user, self::PASSWORD)->assertDontSee(__('two_factor.roles.panel_paused'));
    }

    public function test_signed_in_the_panel_sends_them_to_the_security_tab(): void
    {
        $user = $this->holderWithPassword(['tesoreria']);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('profile.show', ['tab' => 'seguridad']))
            ->assertSessionHas('warning', __('two_factor.roles.panel_paused'));
    }

    public function test_signed_in_without_panel_access_for_another_reason_still_gets_the_403(): void
    {
        $this->actingAs(UserModel::factory()->create())->get('/admin')->assertForbidden();
    }

    /**
     * @param  array<string>  $roleNames
     */
    private function holderWithPassword(array $roleNames): UserModel
    {
        $user = UserModel::factory()->create(['password' => self::PASSWORD]);
        $user->roles()->attach(RoleModel::query()->whereIn('name', $roleNames)->pluck('id')->all());

        return $user;
    }

    /**
     * @return Testable<PanelLogin>
     */
    private function signIn(UserModel $user, string $password): Testable
    {
        return Livewire::test(PanelLogin::class)
            ->fillForm(['email' => $user->email, 'password' => $password])
            ->call('authenticate');
    }
}
