<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Application\Services\SettingsServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Domain\Events\UserLoggedIn;
use App\Domain\TwoFactor\TwoFactorChallengeRule;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\TwoFactor\SignIn\SessionPendingTwoFactorLogin;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The sign-in challenge of the site (core#770): an account with two-factor authentication on
 * gives a code after the password, and nothing signs it in or remembers it before.
 */
final class TwoFactorChallengeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const int NOW = 1_800_000_015;

    private const string PASSWORD = 'correct horse battery staple';

    private const string CHALLENGE = '/iniciar-sesion/verificacion';

    /** @var list<string> */
    private array $recoveryCodes = [];

    private string $secret = '';

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

    public function test_the_password_alone_neither_signs_in_nor_remembers_the_account(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->userWithTwoFactor();
        $this->startSession();
        $before = session()->getId();

        $response = $this->postPassword($user, remember: true);

        $response->assertRedirect(self::CHALLENGE);
        $this->assertGuest();
        $this->assertNoRememberCookies($response);
        $this->assertNotSame($before, session()->getId());
        $this->assertSame((string) $user->id, session(SessionPendingTwoFactorLogin::SESSION_KEY.'.user_id'));
        Event::assertNotDispatched(UserLoggedIn::class);
    }

    public function test_an_account_without_two_factor_signs_in_with_the_password(): void
    {
        $user = UserModel::factory()->create(['password' => self::PASSWORD]);

        $this->postPassword($user)->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session(SessionPendingTwoFactorLogin::SESSION_KEY));
    }

    public function test_remember_sent_by_a_plain_form_does_not_break_the_sign_in(): void
    {
        $user = UserModel::factory()->create(['password' => self::PASSWORD]);

        // A plain HTML form sends "1", not a boolean
        $response = $this->post('/iniciar-sesion', ['email' => $user->email, 'password' => self::PASSWORD, 'remember' => '1']);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($response->getCookie(Auth::guard()->getRecallerName(), false));
    }

    public function test_a_wrong_password_answers_as_before(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post('/iniciar-sesion', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull(session(SessionPendingTwoFactorLogin::SESSION_KEY));
    }

    public function test_the_challenge_page_is_shown_while_a_sign_in_is_pending(): void
    {
        $user = $this->userWithTwoFactor();
        $this->postPassword($user);

        $this->get(self::CHALLENGE)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/TwoFactorChallenge'));
    }

    public function test_without_a_pending_sign_in_the_page_sends_back_to_the_login(): void
    {
        $this->get(self::CHALLENGE)
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', __('auth.two_factor.sign_in_again'));

        $this->post(self::CHALLENGE, ['code' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', __('auth.two_factor.sign_in_again'));
    }

    public function test_a_pending_sign_in_expires_after_five_minutes(): void
    {
        $user = $this->userWithTwoFactor();
        $this->postPassword($user);

        $this->travel(TwoFactorChallengeRule::PENDING_SECONDS + 1)->seconds();

        $this->post(self::CHALLENGE, ['code' => $this->codeAt(1)])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', __('auth.two_factor.sign_in_again'));

        $this->assertGuest();
        $this->assertNull(session(SessionPendingTwoFactorLogin::SESSION_KEY));
    }

    public function test_the_right_code_signs_in_and_seals_the_session(): void
    {
        Event::fake([UserLoggedIn::class]);
        $user = $this->userWithTwoFactor();
        $this->withSession(['url.intended' => url('/eventos')]);
        $this->postPassword($user);

        $response = $this->post(self::CHALLENGE, ['code' => $this->codeAt(1)]);

        $response->assertRedirect(url('/eventos'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($this->sealOf($user), session(TwoFactorSessionSeal::SESSION_KEY));
        $this->assertNull(session(SessionPendingTwoFactorLogin::SESSION_KEY));
        $this->assertNoRememberCookies($response);
        Event::assertDispatched(UserLoggedIn::class, fn (UserLoggedIn $event): bool => $event->userId === (string) $user->id);

        // The next request keeps the session: it holds the seal
        $this->get(route('profile.show'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_me_is_applied_only_after_the_code(): void
    {
        $user = $this->userWithTwoFactor();
        $this->assertNoRememberCookies($this->postPassword($user, remember: true));

        $response = $this->post(self::CHALLENGE, ['code' => $this->codeAt(1)]);

        $recaller = $response->getCookie(Auth::guard()->getRecallerName());
        $challengeCookie = $response->getCookie(TwoFactorSessionSeal::REMEMBER_COOKIE);
        $this->assertNotNull($recaller);
        $this->assertNotNull($challengeCookie);

        // A new visit with only those cookies signs in again
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withCookies([
            Auth::guard()->getRecallerName() => (string) $recaller->getValue(),
            TwoFactorSessionSeal::REMEMBER_COOKIE => (string) $challengeCookie->getValue(),
        ])->get(route('profile.show'))->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_code_is_never_accepted_twice(): void
    {
        $user = $this->userWithTwoFactor();
        $this->postPassword($user);
        $this->post(self::CHALLENGE, ['code' => $this->codeAt(1)]);
        $this->post(route('logout'));

        $this->postPassword($user);
        $this->post(self::CHALLENGE, ['code' => $this->codeAt(1)])
            ->assertSessionHasErrors(['code' => __('auth.two_factor.invalid_code')]);

        $this->assertGuest();
    }

    public function test_a_wrong_code_is_refused_and_logged_without_the_code(): void
    {
        $log = Log::spy();
        $user = $this->userWithTwoFactor();
        $this->postPassword($user);

        $this->post(self::CHALLENGE, ['code' => '000000'])
            ->assertSessionHasErrors(['code' => __('auth.two_factor.invalid_code')]);

        $this->assertGuest();
        $log->shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context): bool => $context === ['user_id' => (string) $user->id, 'reason' => 'invalid_code', 'recovery_code' => false]
        );
    }

    public function test_a_recovery_code_signs_in_and_says_how_many_are_left(): void
    {
        $user = $this->userWithTwoFactor();
        $this->postPassword($user);

        $this->post(self::CHALLENGE, ['code' => $this->recoveryCodes[0], 'recovery' => true])
            ->assertRedirect(route('home'))
            ->assertSessionHas('warning', trans_choice('auth.two_factor.recovery_codes_left', 7, ['count' => 7]));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($this->sealOf($user), session(TwoFactorSessionSeal::SESSION_KEY));
    }

    public function test_a_recovery_code_is_not_taken_as_an_app_code(): void
    {
        $user = $this->userWithTwoFactor();
        $this->postPassword($user);

        $this->post(self::CHALLENGE, ['code' => $this->recoveryCodes[0]])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_attempts_are_throttled_per_pending_sign_in(): void
    {
        $user = $this->userWithTwoFactor();
        $this->postPassword($user);

        for ($attempt = 1; $attempt <= TwoFactorChallengeRule::ATTEMPTS_PER_MINUTE; $attempt++) {
            $this->post(self::CHALLENGE, ['code' => '000000'])->assertSessionHasErrors('code');
        }

        $this->post(self::CHALLENGE, ['code' => $this->codeAt(1)])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertGuest();
    }

    public function test_ten_failures_discard_the_pending_sign_in(): void
    {
        $user = $this->userWithTwoFactor();
        $this->postPassword($user);

        for ($attempt = 1; $attempt < TwoFactorChallengeRule::MAX_FAILURES; $attempt++) {
            if ($attempt % TwoFactorChallengeRule::ATTEMPTS_PER_MINUTE === 0) {
                // Past the limit per minute, but well within the pending sign-in's five minutes
                Carbon::setTestNow(Carbon::now()->addSeconds(61));
            }
            $this->post(self::CHALLENGE, ['code' => '000000'])->assertSessionHasErrors('code');
        }
        Carbon::setTestNow(Carbon::now()->addSeconds(61));

        $this->post(self::CHALLENGE, ['code' => '000000'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', __('auth.two_factor.too_many_failures'));

        $this->assertNull(session(SessionPendingTwoFactorLogin::SESSION_KEY));
        $this->get(self::CHALLENGE)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_signed_in_account_goes_to_where_it_was_going(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->withSession(['url.intended' => url('/eventos')])
            ->get(self::CHALLENGE)
            ->assertRedirect(url('/eventos'));
    }

    public function test_the_challenge_is_closed_when_the_login_is_disabled(): void
    {
        $user = $this->userWithTwoFactor();
        $this->postPassword($user);
        $this->app->make(SettingsServiceInterface::class)->set('auth_login_enabled', '0');

        $this->get(self::CHALLENGE)->assertRedirect('/');
        $this->post(self::CHALLENGE, ['code' => $this->codeAt(1)])->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_registering_still_signs_the_new_account_in(): void
    {
        $this->post('/registro', [
            'name' => 'Persona de prueba',
            'email' => 'nueva@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertRedirect();

        $this->assertAuthenticated();
    }

    private function userWithTwoFactor(): UserModel
    {
        $user = UserModel::factory()->create(['password' => self::PASSWORD]);
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $this->secret = $service->beginSetup((string) $user->id)->secret;
        $this->recoveryCodes = $service->confirmSetup((string) $user->id, $this->codeAt(0));

        return $user;
    }

    private function codeAt(int $offset): string
    {
        return (new Google2FA)->oathTotp($this->secret, intdiv(self::NOW, 30) + $offset);
    }

    /**
     * @return TestResponse<Response>
     */
    private function postPassword(UserModel $user, bool $remember = false): TestResponse
    {
        return $this->post('/iniciar-sesion', ['email' => $user->email, 'password' => self::PASSWORD, 'remember' => $remember]);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertNoRememberCookies(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringStartsNotWith('remember_web_', $cookie->getName());
            $this->assertNotSame(TwoFactorSessionSeal::REMEMBER_COOKIE, $cookie->getName());
        }
    }

    private function sealOf(UserModel $user): string
    {
        return (string) $user->fresh()?->session_seal;
    }
}
