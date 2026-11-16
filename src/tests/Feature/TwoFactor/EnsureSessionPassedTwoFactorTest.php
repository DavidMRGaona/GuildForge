<?php

declare(strict_types=1);

namespace Tests\Feature\TwoFactor;

use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Every session of an account with two-factor authentication passed it, on the site and in the
 * panel, whatever the session driver (core#768).
 */
final class EnsureSessionPassedTwoFactorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const int NOW = 1_800_000_015;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_an_account_without_two_factor_is_left_alone(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->get(route('profile.show'))
            ->assertOk();
    }

    public function test_a_site_session_without_the_seal_is_signed_out(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_site_session_with_the_seal_stays(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);

        $this->actingAs($user)
            ->withSession([TwoFactorSessionSeal::SESSION_KEY => $this->sealOf($user)])
            ->get(route('profile.show'))
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_public_page_still_opens_for_the_signed_out_session(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
        $this->get('/')->assertOk();
    }

    public function test_a_panel_session_without_the_seal_is_signed_out(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->enable($admin);

        $this->actingAs($admin)->get('/admin')->assertRedirect();

        $this->assertGuest();
    }

    public function test_a_panel_session_with_the_seal_stays(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->enable($admin);

        $this->actingAs($admin)
            ->withSession([TwoFactorSessionSeal::SESSION_KEY => $this->sealOf($admin)])
            ->get('/admin')
            ->assertOk();
    }

    public function test_a_session_restored_from_a_current_remember_cookie_is_stamped(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $token = (string) $user->fresh()?->getRememberToken();

        $this->withCookie(Auth::guard()->getRecallerName(), $user->id.'|'.$token.'|'.$user->getAuthPassword())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSessionHas(TwoFactorSessionSeal::SESSION_KEY, $this->sealOf($user));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_remember_cookie_from_before_turning_it_on_no_longer_signs_in(): void
    {
        $user = UserModel::factory()->create(['remember_token' => Str::random(60)]);
        $oldToken = (string) $user->getRememberToken();
        $this->enable($user);

        $this->withCookie(Auth::guard()->getRecallerName(), $user->id.'|'.$oldToken.'|'.$user->getAuthPassword())
            ->get(route('profile.show'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function enable(UserModel $user): void
    {
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $setup = $service->beginSetup((string) $user->id);
        $service->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30)));
    }

    private function sealOf(UserModel $user): string
    {
        return (string) UserTwoFactorModel::query()->findOrFail($user->id)->session_seal;
    }
}
