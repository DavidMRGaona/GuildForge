<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Laravel skips request forgery checks while tests run; these tests turn the
 * check back on for the public site and the admin panel.
 */
final class RequestForgeryProtectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string TOKEN = 'known-session-token';

    private const string WRONG_TOKEN = 'forged-session-token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(PreventRequestForgery::class, static fn (Application $app): PreventRequestForgery => new class ($app, $app['encrypter']) extends PreventRequestForgery {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    public function test_the_admin_panel_uses_the_request_forgery_middleware(): void
    {
        $middleware = Filament::getPanel('admin')->getMiddleware();

        $this->assertContains(PreventRequestForgery::class, $middleware);
        $this->assertNotContains(VerifyCsrfToken::class, $middleware);
    }

    public function test_a_public_form_without_a_token_is_rejected(): void
    {
        $this->post('/iniciar-sesion', $this->wrongCredentials())->assertStatus(419);
    }

    public function test_a_public_form_with_the_session_token_is_accepted(): void
    {
        $this->withSession(['_token' => self::TOKEN])
            ->post('/iniciar-sesion', [...$this->wrongCredentials(), '_token' => self::TOKEN])
            ->assertSessionHasErrors('email');
    }

    public function test_a_public_form_with_a_wrong_token_is_rejected(): void
    {
        $this->withSession(['_token' => self::TOKEN])
            ->post('/iniciar-sesion', [...$this->wrongCredentials(), '_token' => self::WRONG_TOKEN])
            ->assertStatus(419);
    }

    public function test_an_inertia_request_with_the_xsrf_cookie_is_accepted(): void
    {
        $this->withSession(['_token' => self::TOKEN])
            ->withHeader('X-XSRF-TOKEN', $this->xsrfHeader(self::TOKEN))
            ->post('/iniciar-sesion', $this->wrongCredentials())
            ->assertSessionHasErrors('email');
    }

    public function test_an_inertia_request_with_a_wrong_xsrf_cookie_is_rejected(): void
    {
        $this->withSession(['_token' => self::TOKEN])
            ->withHeader('X-XSRF-TOKEN', $this->xsrfHeader(self::WRONG_TOKEN))
            ->post('/iniciar-sesion', $this->wrongCredentials())
            ->assertStatus(419);
    }

    public function test_a_same_origin_browser_request_is_accepted_without_a_token(): void
    {
        $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('/iniciar-sesion', $this->wrongCredentials())
            ->assertSessionHasErrors('email');
    }

    public function test_a_cross_site_request_without_a_token_is_rejected(): void
    {
        $this->withHeader('Sec-Fetch-Site', 'cross-site')
            ->post('/iniciar-sesion', $this->wrongCredentials())
            ->assertStatus(419);
    }

    public function test_the_admin_panel_rejects_a_logout_without_a_token(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->post('/admin/logout')->assertStatus(419);
        $this->assertAuthenticated();
    }

    public function test_the_admin_panel_rejects_a_logout_with_a_wrong_token(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->withSession(['_token' => self::TOKEN])
            ->post('/admin/logout', ['_token' => self::WRONG_TOKEN])
            ->assertStatus(419);
        $this->assertAuthenticated();
    }

    public function test_the_admin_panel_accepts_a_logout_with_the_session_token(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->withSession(['_token' => self::TOKEN])
            ->post('/admin/logout', ['_token' => self::TOKEN])
            ->assertRedirect();
        $this->assertGuest();
    }

    private function xsrfHeader(string $token): string
    {
        $encrypter = $this->app['encrypter'];

        return $encrypter->encrypt(CookieValuePrefix::create('XSRF-TOKEN', $encrypter->getKey()).$token, false);
    }

    /**
     * @return array{email: string, password: string}
     */
    private function wrongCredentials(): array
    {
        return ['email' => 'nobody@example.com', 'password' => 'wrong-password'];
    }
}
