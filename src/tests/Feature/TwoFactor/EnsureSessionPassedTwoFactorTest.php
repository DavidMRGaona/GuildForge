<?php

declare(strict_types=1);

namespace Tests\Feature\TwoFactor;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Http\Middleware\EnsureSessionPassedTwoFactor;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Filament\Facades\Filament;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Every session of an account with a seal holds it, on the site and in the panel, whatever the
 * session driver (core#768).
 */
final class EnsureSessionPassedTwoFactorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const int NOW = 1_800_000_015;

    private const string PASSWORD = 'correct horse battery staple';

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

    public function test_an_account_that_never_had_a_seal_is_left_alone(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->get(route('profile.show'))
            ->assertOk();
    }

    public function test_a_site_session_opened_before_turning_it_on_is_signed_out(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);

        $this->actingAs($this->fresh($user))
            ->get(route('profile.show'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_site_session_holding_the_seal_stays(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);

        $this->actingAs($this->fresh($user))
            ->withSession([TwoFactorSessionSeal::SESSION_KEY => $this->sealOf($user)])
            ->get(route('profile.show'))
            ->assertOk();

        $this->assertAuthenticated();
    }

    public function test_a_session_opened_before_turning_it_off_is_signed_out(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $before = $this->sealOf($user);
        $this->app->make(TwoFactorServiceInterface::class)->disable((string) $user->id);

        $this->actingAs($this->fresh($user))
            ->withSession([TwoFactorSessionSeal::SESSION_KEY => $before])
            ->get(route('profile.show'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_signing_in_without_two_factor_copies_the_seal(): void
    {
        $user = UserModel::factory()->create(['password' => self::PASSWORD]);
        $this->enable($user);
        $this->app->make(TwoFactorServiceInterface::class)->disable((string) $user->id);

        $this->post('/iniciar-sesion', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHas(TwoFactorSessionSeal::SESSION_KEY, $this->sealOf($user));

        $this->get(route('profile.show'))->assertOk();
        $this->assertAuthenticated();
    }

    public function test_signing_in_with_two_factor_on_without_the_challenge_does_not_stay(): void
    {
        $user = UserModel::factory()->create(['password' => self::PASSWORD]);
        $this->enable($user);

        // A seal left in the session from before is dropped too
        $this->withSession([TwoFactorSessionSeal::SESSION_KEY => $this->sealOf($user)])
            ->post('/iniciar-sesion', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionMissing(TwoFactorSessionSeal::SESSION_KEY);

        $this->get(route('profile.show'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_panel_session_without_the_seal_is_signed_out(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->enable($admin);

        $this->actingAs($this->fresh($admin))->get('/admin')->assertRedirect();

        $this->assertGuest();
    }

    public function test_a_panel_session_holding_the_seal_stays(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->enable($admin);

        $this->actingAs($this->fresh($admin))
            ->withSession([TwoFactorSessionSeal::SESSION_KEY => $this->sealOf($admin)])
            ->get('/admin')
            ->assertOk();
    }

    public function test_the_guards_remember_cookie_alone_never_skips_the_challenge(): void
    {
        $user = UserModel::factory()->create(['password' => self::PASSWORD]);
        $this->enable($user);

        // The password step with «remember me»: today it issues Laravel's cookie with the password alone
        $login = $this->post('/iniciar-sesion', ['email' => $user->email, 'password' => self::PASSWORD, 'remember' => true]);
        $recaller = $login->getCookie(Auth::guard()->getRecallerName(), false);
        $this->assertNotNull($recaller);

        $this->restartWith([Auth::guard()->getRecallerName() => (string) $recaller->getValue()])
            ->get(route('profile.show'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_remember_cookie_from_the_challenge_signs_in_again(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $fresh = $this->fresh($user);
        $recaller = $fresh->id.'|'.$fresh->getRememberToken().'|'.$fresh->getAuthPassword();

        $this->restartWith([
            Auth::guard()->getRecallerName() => $recaller,
            TwoFactorSessionSeal::REMEMBER_COOKIE => $this->challengeCookie($user),
        ])
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSessionHas(TwoFactorSessionSeal::SESSION_KEY, $this->sealOf($user));

        $this->assertAuthenticated();
    }

    public function test_a_challenge_cookie_of_another_account_or_a_forged_one_does_not_help(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->enable($user);
        $this->enable($other);
        $fresh = $this->fresh($user);
        $recaller = $fresh->id.'|'.$fresh->getRememberToken().'|'.$fresh->getAuthPassword();

        foreach ([$this->challengeCookie($other), 'forged', str_repeat('a', 64)] as $cookie) {
            $this->restartWith([Auth::guard()->getRecallerName() => $recaller, TwoFactorSessionSeal::REMEMBER_COOKIE => $cookie])
                ->get(route('profile.show'))
                ->assertRedirect(route('login'));

            $this->assertGuest();
        }
    }

    public function test_turning_it_off_or_on_again_invalidates_the_challenge_cookie(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $cookie = $this->challengeCookie($user);
        $seal = $this->app->make(TwoFactorSessionSealInterface::class);
        $request = Request::create('/perfil', cookies: [TwoFactorSessionSeal::REMEMBER_COOKIE => $cookie]);
        $this->assertTrue($seal->rememberedAfterChallenge($request, (string) $user->id));

        $this->app->make(TwoFactorServiceInterface::class)->disable((string) $user->id);
        $this->assertFalse($seal->rememberedAfterChallenge($request, (string) $user->id));

        $this->enable($user, offset: 1);
        $this->assertFalse($seal->rememberedAfterChallenge($request, (string) $user->id));
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

    public function test_the_panel_sends_to_its_own_login(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->enable($admin);

        $this->actingAs($this->fresh($admin))
            ->get('/admin')
            ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

        $this->assertGuest();
    }

    public function test_a_livewire_update_without_the_seal_redirects_to_the_panel_login(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->enable($admin);
        $this->actingAs($this->fresh($admin));
        $html = (string) $this->withSession([TwoFactorSessionSeal::SESSION_KEY => $this->sealOf($admin)])
            ->get('/admin')
            ->assertOk()
            ->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $this->assertNotEmpty($matches);

        $response = $this->withSession([TwoFactorSessionSeal::SESSION_KEY => 'stale'])
            ->withHeaders(['X-Livewire' => 'true'])
            ->postJson(Livewire::getUpdateUri(), [
                'components' => [['snapshot' => html_entity_decode($matches[1], ENT_QUOTES), 'updates' => [], 'calls' => []]],
            ]);

        $this->assertGuest();
        $response->assertOk()
            ->assertJsonPath('components.0.effects.redirect', Filament::getPanel('admin')->getLoginUrl())
            ->assertJsonPath('components.0.snapshot', html_entity_decode($matches[1], ENT_QUOTES));
    }

    public function test_signing_out_while_impersonating_closes_the_impersonation(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $target = UserModel::factory()->create();
        $this->enable($admin);
        $this->actingAs($this->fresh($admin));
        $this->app->make(ImpersonationServiceInterface::class)->start($this->fresh($admin), $target, ImpersonationMode::ReadOnly);

        $this->get(route('profile.show'))->assertRedirect(route('login'));

        $this->assertGuest();
        $entry = DB::table('impersonation_log')->where('impersonator_id', $admin->id)->first();
        $this->assertNotNull($entry);
        $this->assertNotNull($entry->ended_at);
        $this->assertSame(ImpersonationEndReason::Revoked->value, $entry->ended_reason);
    }

    public function test_while_impersonating_the_administrators_seal_is_checked(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $target = UserModel::factory()->create();
        $this->enable($admin);
        $this->enable($target);

        // The session is the administrator's, holding their seal, while the guard holds the target
        $this->assertTrue($this->passesWhileImpersonating($admin, $target, $this->sealOf($admin)));
        $this->assertFalse($this->passesWhileImpersonating($admin, $target, $this->sealOf($target)));
        $this->assertFalse($this->passesWhileImpersonating($admin, $target, null));
    }

    private function passesWhileImpersonating(UserModel $admin, UserModel $target, ?string $held): bool
    {
        $impersonation = $this->createStub(ImpersonationContextInterface::class);
        $impersonation->method('isActive')->willReturn(true);
        $impersonation->method('impersonator')->willReturn($this->fresh($admin));
        $impersonation->method('target')->willReturn($this->fresh($target));

        $session = new Store('test', new ArraySessionHandler(10), Str::random(40));
        if ($held !== null) {
            $session->put(TwoFactorSessionSeal::SESSION_KEY, $held);
        }

        $request = Request::create('/perfil');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn (): UserModel => $this->fresh($target));
        Auth::guard()->setUser($this->fresh($target));

        $middleware = new EnsureSessionPassedTwoFactor(
            $this->app->make(AuthFactory::class),
            $impersonation,
            $this->createStub(ImpersonationServiceInterface::class),
            $this->app->make(TwoFactorSessionSealInterface::class),
            $this->app->make(CookieJar::class),
        );

        try {
            $middleware->handle($request, static fn (): Response => new Response('ok'));

            return true;
        } catch (AuthenticationException) {
            return false;
        }
    }

    /**
     * What the challenge (core#770) issues after the code with «remember me».
     */
    private function challengeCookie(UserModel $user): string
    {
        $this->app->make(TwoFactorSessionSealInterface::class)->rememberAfterChallenge((string) $user->id);
        $cookie = $this->app->make(CookieJar::class)->queued(TwoFactorSessionSeal::REMEMBER_COOKIE);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->app->make(CookieJar::class)->unqueue(TwoFactorSessionSeal::REMEMBER_COOKIE);

        return (string) $cookie->getValue();
    }

    /**
     * A new browser visit: no session, nobody signed in, only these cookies.
     *
     * @param  array<string, string>  $cookies
     */
    private function restartWith(array $cookies): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->withCookies($cookies);
    }

    private function enable(UserModel $user, int $offset = 0): void
    {
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $setup = $service->beginSetup((string) $user->id);
        $service->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30) + $offset));
    }

    private function fresh(UserModel $user): UserModel
    {
        $fresh = $user->fresh();
        $this->assertNotNull($fresh);

        return $fresh;
    }

    private function sealOf(UserModel $user): string
    {
        return (string) $this->fresh($user)->session_seal;
    }
}
