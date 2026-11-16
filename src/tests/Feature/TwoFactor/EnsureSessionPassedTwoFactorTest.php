<?php

declare(strict_types=1);

namespace Tests\Feature\TwoFactor;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Http\Middleware\EnsureSessionPassedTwoFactor;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

        $this->post('/iniciar-sesion', ['email' => $user->email, 'password' => self::PASSWORD])
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

    public function test_a_session_restored_from_a_current_remember_cookie_is_stamped(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $fresh = $this->fresh($user);

        $this->withCookie(Auth::guard()->getRecallerName(), $fresh->id.'|'.$fresh->getRememberToken().'|'.$fresh->getAuthPassword())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSessionHas(TwoFactorSessionSeal::SESSION_KEY, $this->sealOf($user));

        $this->assertAuthenticated();
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
            $this->app->make(TwoFactorSessionSealInterface::class),
        );

        try {
            $middleware->handle($request, static fn (): Response => new Response('ok'));

            return true;
        } catch (AuthenticationException) {
            return false;
        }
    }

    private function enable(UserModel $user): void
    {
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $setup = $service->beginSetup((string) $user->id);
        $service->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30)));
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
