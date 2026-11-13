<?php

declare(strict_types=1);

namespace Tests\Feature\Impersonation;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Filament\Resources\UserResource;
use App\Infrastructure\Impersonation\ImpersonationContext;
use App\Infrastructure\Impersonation\ImpersonationSessionState;
use App\Infrastructure\Persistence\Eloquent\Models\ImpersonationLogEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Http\Middleware\AuthenticateSession as FilamentAuthenticateSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The administrator's session survives the impersonation: the session check keeps the
 * administrator's password hash, «Volver a mi cuenta» ends it, and signing out signs the
 * administrator out without touching the other account's «remember me».
 */
final class ImpersonationSessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    private UserModel $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
        // Panel access, so the panel and its Livewire requests open as this account
        // and a password of its own, so a stored hash tells whose it is
        $this->editor = UserModel::factory()->editor()->create([
            'password' => Hash::make('another-password'),
            'remember_token' => 'editor-remember-token',
        ]);
    }

    /**
     * Livewire runs both checks again after the web group has applied the impersonation,
     * so each one meets the other account in the guard: as built by the container (what the
     * panel and Livewire's persistent middleware get), it checks and stores the administrator's.
     */
    #[DataProvider('sessionChecks')]
    public function test_each_session_check_keeps_the_administrators_hash(string $middleware): void
    {
        $this->actingAs($this->admin);
        app(ImpersonationContext::class)->activate($this->admin, $this->editor, ImpersonationMode::ReadOnly, 'log-id');
        Auth::setUser($this->editor);
        $session = app(Session::class);
        $session->put('password_hash_web', $this->adminPasswordHash());
        $request = Request::create('/admin');
        $request->setLaravelSession($session);
        $request->setUserResolver(static fn (): ?Authenticatable => Auth::user());

        /** @var AuthenticateSession $check */
        $check = $this->app->make($middleware);
        $response = $check->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame($this->adminPasswordHash(), $session->get('password_hash_web'));
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function sessionChecks(): array
    {
        return [
            'Laravel' => [AuthenticateSession::class],
            'Filament' => [FilamentAuthenticateSession::class],
        ];
    }

    public function test_the_administrators_session_survives_panel_and_livewire_requests(): void
    {
        $this->startImpersonating();

        $page = $this->get('/admin')->assertOk();
        for ($i = 0; $i < 3; $i++) {
            $this->livewireUpdate((string) $page->getContent())->assertOk();
        }

        $this->assertSame($this->adminPasswordHash(), app(Session::class)->get('password_hash_web'));

        $this->post(route('impersonation.stop'));

        // Back as the administrator, still signed in
        $this->get('/admin')->assertOk();
        $this->assertTrue(Auth::user()?->is($this->admin));
    }

    public function test_back_to_my_account_ends_the_impersonation(): void
    {
        $this->startImpersonating();
        $idBefore = app(Session::class)->getId();

        $response = $this->post(route('impersonation.stop'));

        $response->assertRedirect(UserResource::getUrl('edit', ['record' => $this->editor->id]));
        $entry = ImpersonationLogEntryModel::query()->sole();
        $this->assertSame(ImpersonationEndReason::Stopped, $entry->ended_reason);
        $this->assertNotNull($entry->ended_at);
        $this->assertFalse(app(Session::class)->has(ImpersonationSessionState::SESSION_KEY));
        $this->assertNotSame($idBefore, app(Session::class)->getId());
        $this->assertTrue(Auth::user()?->is($this->admin));
    }

    public function test_back_to_my_account_works_while_the_site_is_in_maintenance(): void
    {
        $member = UserModel::factory()->create();
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');
        $this->actingAs($this->admin);
        app(ImpersonationServiceInterface::class)->start($this->admin, $member, ImpersonationMode::ReadOnly);

        // The other account gets the maintenance page, as it would
        $this->get(route('home'))->assertStatus(503);

        $this->post(route('impersonation.stop'))
            ->assertRedirect(UserResource::getUrl('edit', ['record' => $member->id]));
        $this->assertTrue(Auth::user()?->is($this->admin));
    }

    public function test_back_to_my_account_works_for_an_account_that_must_verify_its_email(): void
    {
        $member = UserModel::factory()->unverified()->create();
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
        $this->actingAs($this->admin);
        app(ImpersonationServiceInterface::class)->start($this->admin, $member, ImpersonationMode::ReadOnly);

        // The other account is sent to verify its address, as it would
        $this->get(route('site-notifications.index'))->assertRedirect(route('verification.notice'));

        $this->post(route('impersonation.stop'))
            ->assertRedirect(UserResource::getUrl('edit', ['record' => $member->id]));
    }

    public function test_back_to_my_account_without_an_impersonation_goes_home(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('impersonation.stop'))->assertRedirect(route('home'));
    }

    public function test_back_to_my_account_needs_a_session(): void
    {
        $this->post(route('impersonation.stop'))->assertRedirect(route('login'));
    }

    public function test_signing_out_of_the_site_signs_the_administrator_out(): void
    {
        $this->startImpersonating();
        $adminToken = $this->admin->fresh()?->getRememberToken();

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertSame('editor-remember-token', $this->editor->fresh()?->getRememberToken());
        $this->assertNotSame($adminToken, $this->admin->fresh()?->getRememberToken());
        $this->assertSame(ImpersonationEndReason::Logout, ImpersonationLogEntryModel::query()->sole()->ended_reason);
    }

    public function test_signing_out_of_the_panel_signs_the_administrator_out(): void
    {
        $this->startImpersonating();

        $this->post(route('filament.admin.auth.logout'))->assertRedirect();

        $this->assertGuest();
        $this->assertSame('editor-remember-token', $this->editor->fresh()?->getRememberToken());
        $this->assertSame(ImpersonationEndReason::Logout, ImpersonationLogEntryModel::query()->sole()->ended_reason);
    }

    private function adminPasswordHash(): string
    {
        return Auth::guard()->hashPasswordForCookie($this->admin->getAuthPassword());
    }

    private function startImpersonating(): void
    {
        $this->actingAs($this->admin);
        app(ImpersonationServiceInterface::class)->start($this->admin, $this->editor, ImpersonationMode::ReadOnly);
    }

    /**
     * A Livewire round trip of the page's own component, as the browser sends it: the panel's
     * session check runs again as persistent middleware.
     *
     * @return TestResponse<Response>
     */
    private function livewireUpdate(string $html): TestResponse
    {
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $html, $matches), 'No Livewire component on the page');

        return $this->withHeaders(['X-Livewire' => 'true'])->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => html_entity_decode($matches[1], ENT_QUOTES),
                'updates' => (object) [],
                'calls' => [],
            ]],
        ]);
    }
}
