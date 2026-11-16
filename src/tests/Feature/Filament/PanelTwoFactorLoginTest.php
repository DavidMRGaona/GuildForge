<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Domain\TwoFactor\TwoFactorChallengeRule;
use App\Filament\Auth\PanelLogin;
use App\Filament\Auth\TwoFactorAuthenticationProvider;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Filament\Facades\Filament;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * The panel's login asks for the second factor through Filament's own step, backed by the core
 * service (core#770): nothing is signed in or remembered before the code.
 */
final class PanelTwoFactorLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const int NOW = 1_800_000_015;

    private const string PASSWORD = 'correct horse battery staple';

    private const string CODE_FIELD = 'data.multiFactor.'.TwoFactorAuthenticationProvider::ID.'.code';

    private string $secret = '';

    /** @var list<string> */
    private array $recoveryCodes = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_panel_uses_the_core_login_page_and_provider(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame(PanelLogin::class, $panel->getLoginRouteAction());
        $this->assertContainsOnlyInstancesOf(TwoFactorAuthenticationProvider::class, $panel->getMultiFactorAuthenticationProviders());
        $this->assertFalse($panel->isMultiFactorAuthenticationRequired());
    }

    public function test_an_account_without_two_factor_signs_in_with_the_password(): void
    {
        $user = UserModel::factory()->admin()->create(['password' => self::PASSWORD]);

        $this->passwordStep($user)->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_password_alone_neither_signs_in_nor_remembers_the_account(): void
    {
        $user = $this->adminWithTwoFactor();

        $login = $this->passwordStep($user, remember: true);

        $this->assertNotNull($login->get('userUndertakingMultiFactorAuthentication'));
        $this->assertGuest();
        $this->assertNoQueuedRememberCookies();
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $user = $this->adminWithTwoFactor();

        $this->passwordStep($user)
            ->fillForm($this->challenge('000000'), 'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertHasErrors([self::CODE_FIELD]);

        $this->assertGuest();
    }

    public function test_the_right_code_signs_in_and_seals_the_session(): void
    {
        $user = $this->adminWithTwoFactor();

        $this->passwordStep($user)
            ->fillForm($this->challenge($this->codeAt(1)), 'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame((string) $user->fresh()?->session_seal, session(TwoFactorSessionSeal::SESSION_KEY));
        $this->assertNoQueuedRememberCookies();

        // The next panel request keeps the session: it holds the seal
        $this->get('/admin')->assertOk();
    }

    public function test_remember_me_is_applied_only_after_the_code(): void
    {
        $user = $this->adminWithTwoFactor();

        $login = $this->passwordStep($user, remember: true);
        $this->assertNoQueuedRememberCookies();

        $login->fillForm($this->challenge($this->codeAt(1)), 'multiFactorChallengeForm')->call('authenticate');

        $cookies = $this->app->make(CookieJar::class);
        $this->assertNotNull($cookies->queued(Auth::guard()->getRecallerName()));
        $this->assertNotNull($cookies->queued(TwoFactorSessionSeal::REMEMBER_COOKIE));
    }

    public function test_a_code_is_never_accepted_twice(): void
    {
        $user = $this->adminWithTwoFactor();
        $this->passwordStep($user)
            ->fillForm($this->challenge($this->codeAt(1)), 'multiFactorChallengeForm')
            ->call('authenticate');
        Auth::logout();

        $this->passwordStep($user)
            ->fillForm($this->challenge($this->codeAt(1)), 'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertHasErrors([self::CODE_FIELD]);

        $this->assertGuest();
    }

    public function test_a_recovery_code_signs_in(): void
    {
        $user = $this->adminWithTwoFactor();

        $this->passwordStep($user)
            ->fillForm($this->challenge($this->recoveryCodes[0], recovery: true), 'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertNotified(trans_choice('auth.two_factor.recovery_codes_left', 7, ['count' => 7]));

        $this->assertAuthenticatedAs($user);
        $this->assertSame((string) $user->fresh()?->session_seal, session(TwoFactorSessionSeal::SESSION_KEY));
    }

    public function test_a_pending_sign_in_expires_after_five_minutes(): void
    {
        $user = $this->adminWithTwoFactor();
        $login = $this->passwordStep($user);

        Carbon::setTestNow(Carbon::now()->addSeconds(TwoFactorChallengeRule::PENDING_SECONDS + 1));

        $login->fillForm($this->challenge($this->codeAt(10)), 'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertSet('userUndertakingMultiFactorAuthentication', null)
            ->assertNotified(__('auth.two_factor.sign_in_again'));

        $this->assertGuest();
    }

    public function test_ten_failures_send_back_to_the_password(): void
    {
        $user = $this->adminWithTwoFactor();
        $login = $this->passwordStep($user);

        for ($attempt = 1; $attempt < TwoFactorChallengeRule::MAX_FAILURES; $attempt++) {
            if ($attempt % TwoFactorChallengeRule::ATTEMPTS_PER_MINUTE === 0) {
                // Past Filament's limit per minute, well within the five minutes
                Carbon::setTestNow(Carbon::now()->addSeconds(61));
            }
            $login->fillForm($this->challenge('000000'), 'multiFactorChallengeForm')
                ->call('authenticate')
                ->assertHasErrors([self::CODE_FIELD]);
        }
        Carbon::setTestNow(Carbon::now()->addSeconds(61));

        $login->fillForm($this->challenge('000000'), 'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertSet('userUndertakingMultiFactorAuthentication', null)
            ->assertNotified(__('auth.two_factor.too_many_failures'));

        $this->assertGuest();
    }

    public function test_a_code_validated_twice_in_one_request_keeps_the_first_answer(): void
    {
        $user = $this->adminWithTwoFactor();
        $provider = new TwoFactorAuthenticationProvider;
        $code = $this->codeAt(1);

        foreach ([1, 2] as $_) {
            $this->assertTrue($provider->check((string) $user->id, $code, false)->passed);
        }

        // A new request (the login page forgets what was checked) sees the code as used
        $provider->forgetChecks();
        $this->assertFalse($provider->check((string) $user->id, $code, false)->passed);
    }

    public function test_the_challenge_field_refuses_through_the_core_service(): void
    {
        $user = $this->adminWithTwoFactor();
        $provider = new TwoFactorAuthenticationProvider;
        $rule = $provider->codeRule((string) $user->id, static fn (): bool => false);

        $this->assertTrue(Validator::make(['code' => '000000'], ['code' => [$rule]])->fails());
        $this->assertFalse(Validator::make(['code' => $this->codeAt(1)], ['code' => [$rule]])->fails());
    }

    private function adminWithTwoFactor(): UserModel
    {
        $user = UserModel::factory()->admin()->create(['password' => self::PASSWORD]);
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $this->secret = $service->beginSetup((string) $user->id)->secret;
        $this->recoveryCodes = $service->confirmSetup((string) $user->id, $this->codeAt(0));

        return $user;
    }

    private function codeAt(int $offset): string
    {
        return (new Google2FA)->oathTotp($this->secret, intdiv(Carbon::now()->getTimestamp(), 30) + $offset);
    }

    /**
     * @return array<string, mixed>
     */
    private function challenge(string $code, bool $recovery = false): array
    {
        return [TwoFactorAuthenticationProvider::ID => ['code' => $code, 'recovery' => $recovery]];
    }

    /**
     * @return Testable<PanelLogin>
     */
    private function passwordStep(UserModel $user, bool $remember = false): Testable
    {
        return Livewire::test(PanelLogin::class)
            ->fillForm(['email' => $user->email, 'password' => self::PASSWORD, 'remember' => $remember])
            ->call('authenticate');
    }

    private function assertNoQueuedRememberCookies(): void
    {
        $cookies = $this->app->make(CookieJar::class);
        $this->assertNull($cookies->queued(Auth::guard()->getRecallerName()));
        $this->assertNull($cookies->queued(TwoFactorSessionSeal::REMEMBER_COOKIE));
    }
}
