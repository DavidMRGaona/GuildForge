<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Application\Services\SettingsServiceInterface;
use App\Http\Middleware\HandleInertiaRequests;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

final class EnsureEmailIsVerifiedWhenRequiredTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string CUTOFF = '2026-01-01T00:00:00+00:00';

    private const string PROTECTED_URL = '/_test/verification/protected';

    private const string PUBLIC_URL = '/_test/verification/public';

    protected function setUp(): void
    {
        parent::setUp();

        // Registered the way ModuleServiceProvider::loadRoutes() registers module routes
        Route::middleware('web')->group(function (): void {
            Route::middleware('auth')->group(function (): void {
                Route::get(self::PROTECTED_URL, static fn (): string => 'protected')->name('test.verification.protected');
                Route::post(self::PROTECTED_URL, static fn (): string => 'protected');
            });
            Route::get(self::PUBLIC_URL, static fn (): string => 'public');
        });
        Route::getRoutes()->refreshNameLookups();

        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', self::CUTOFF);
    }

    public function test_an_unverified_account_reaches_authenticated_routes_when_the_setting_is_off(): void
    {
        $this->actingAs($this->newUnverifiedUser());

        $this->get(self::PROTECTED_URL)->assertOk()->assertSee('protected');
        $this->get(route('profile.show'))->assertOk();
    }

    public function test_a_new_unverified_account_is_sent_to_the_notice_from_a_core_authenticated_route(): void
    {
        $this->requireVerification();
        $this->actingAs($this->newUnverifiedUser());

        $this->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('verification.notice'));
    }

    public function test_a_new_unverified_account_is_sent_to_the_notice_from_a_module_route(): void
    {
        $this->requireVerification();
        $this->actingAs($this->newUnverifiedUser());

        $this->get(self::PROTECTED_URL)->assertRedirect(route('verification.notice'));
        $this->post(self::PROTECTED_URL)->assertRedirect(route('verification.notice'));
    }

    public function test_an_inertia_visit_is_sent_to_the_notice(): void
    {
        $this->requireVerification();
        $this->actingAs($this->newUnverifiedUser());

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->get(self::PROTECTED_URL)
            ->assertRedirect(route('verification.notice'));
    }

    public function test_the_page_asked_for_is_remembered_for_after_the_verification(): void
    {
        $this->requireVerification();
        $this->actingAs($this->newUnverifiedUser());

        $this->get(self::PROTECTED_URL)->assertRedirect(route('verification.notice'));

        $this->assertSame(url(self::PROTECTED_URL), session('url.intended'));
    }

    public function test_a_json_request_gets_a_403_with_a_translated_message(): void
    {
        $this->requireVerification();
        $this->actingAs($this->newUnverifiedUser());

        $this->getJson(self::PROTECTED_URL)
            ->assertForbidden()
            ->assertExactJson(['message' => __('auth.email_verification_required')]);
        $this->putJson(route('profile.password'), [])
            ->assertForbidden()
            ->assertJson(['message' => __('auth.email_verification_required')]);
    }

    public function test_an_unverified_account_created_before_the_cutoff_keeps_working(): void
    {
        $this->requireVerification();
        $this->actingAs(UserModel::factory()->unverified()->create(['created_at' => '2025-06-01 12:00:00']));

        $this->get(self::PROTECTED_URL)->assertOk();
        $this->getJson(self::PROTECTED_URL)->assertOk();
    }

    public function test_a_verified_account_is_never_stopped(): void
    {
        $this->requireVerification();
        $this->actingAs(UserModel::factory()->create());

        $this->get(self::PROTECTED_URL)->assertOk();
    }

    public function test_an_anonymized_account_is_not_stopped(): void
    {
        $this->requireVerification();
        $this->actingAs(UserModel::factory()->unverified()->create(['anonymized_at' => now()]));

        $this->get(self::PROTECTED_URL)->assertOk();
    }

    public function test_every_unverified_account_is_stopped_when_the_cutoff_is_missing(): void
    {
        $this->requireVerification();
        DB::table('settings')->where('key', 'email_verification_enforced_since')->delete();
        app(SettingsServiceInterface::class)->clearCache();
        $this->actingAs(UserModel::factory()->unverified()->create(['created_at' => '2020-01-01 12:00:00']));

        $this->get(self::PROTECTED_URL)->assertRedirect(route('verification.notice'));
    }

    public function test_guests_are_left_to_the_auth_middleware(): void
    {
        $this->requireVerification();

        $this->get(self::PROTECTED_URL)->assertRedirect(route('login'));
    }

    public function test_public_pages_stay_open_to_an_unverified_account(): void
    {
        $this->requireVerification();
        $this->actingAs($this->newUnverifiedUser());

        $this->get(self::PUBLIC_URL)->assertOk()->assertSee('public');
        $this->get(route('home'))->assertOk();
        $this->get(route('events.index'))->assertOk();
    }

    public function test_the_notice_stays_reachable(): void
    {
        $this->requireVerification();
        $this->actingAs($this->newUnverifiedUser());

        $this->get(route('verification.notice'))->assertOk();
    }

    public function test_the_verification_email_can_be_sent_again(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = $this->newUnverifiedUser();
        $this->actingAs($user);

        $this->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_the_verification_link_works_and_returns_to_the_page_asked_for(): void
    {
        $this->requireVerification();
        $user = $this->newUnverifiedUser();
        $this->actingAs($user);
        $this->get(self::PROTECTED_URL)->assertRedirect(route('verification.notice'));

        $this->get($this->verificationUrl($user))->assertRedirect(url(self::PROTECTED_URL));

        $fresh = $user->fresh();
        $this->assertNotNull($fresh?->email_verified_at);
        // A real session reloads the user on every request; the test guard keeps its instance
        $this->actingAs($fresh);
        $this->get(self::PROTECTED_URL)->assertOk();
    }

    public function test_logging_out_stays_possible(): void
    {
        $this->requireVerification();
        $this->actingAs($this->newUnverifiedUser());

        $this->post(route('logout'))->assertRedirect();

        $this->assertGuest();
    }

    public function test_the_email_can_be_corrected_from_the_profile(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = $this->newUnverifiedUser();
        $this->actingAs($user);

        $this->get(route('profile.show'))->assertOk();
        $this->from(route('profile.show'))
            ->post(route('profile.update'), ['name' => $user->name, 'email' => 'corrected@example.com'])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasNoErrors();

        $this->assertSame('corrected@example.com', $user->fresh()?->pending_email);
    }

    public function test_confirming_the_corrected_email_verifies_the_account(): void
    {
        $this->requireVerification();
        $user = $this->newUnverifiedUser();
        $user->update(['pending_email' => 'corrected@example.com']);
        $this->actingAs($user);

        $url = URL::temporarySignedRoute('verification.pending-email', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1('corrected@example.com'),
        ]);
        $this->get($url)->assertRedirect(route('profile.show'));

        $fresh = $user->fresh();
        $this->assertSame('corrected@example.com', $fresh?->email);
        $this->assertNotNull($fresh?->email_verified_at);
        $this->actingAs($fresh);
        $this->get(self::PROTECTED_URL)->assertOk();
    }

    public function test_an_unverified_admin_is_not_locked_out_of_the_panel(): void
    {
        $this->requireVerification();
        $this->actingAs(UserModel::factory()->admin()->unverified()->create());

        $this->get('/admin')->assertOk();
    }

    public function test_livewire_endpoints_are_not_stopped(): void
    {
        $this->requireVerification();
        $this->actingAs(UserModel::factory()->admin()->unverified()->create());

        $response = $this->withHeaders(['X-Livewire' => 'true'])
            ->postJson(Livewire::getUpdateUri(), ['components' => []]);

        $this->assertNotSame(403, $response->getStatusCode());
        $this->assertFalse($response->isRedirect(route('verification.notice')));
    }

    public function test_a_new_registration_ends_up_on_the_notice(): void
    {
        Notification::fake();
        $this->requireVerification();

        $this->post(route('register'), [
            'name' => 'Nueva cuenta',
            'email' => 'new@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ])->assertRedirect();

        $this->assertAuthenticated();
        $this->get(self::PROTECTED_URL)->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk();
    }

    private function requireVerification(): void
    {
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
    }

    private function newUnverifiedUser(): UserModel
    {
        return UserModel::factory()->unverified()->create();
    }

    private function verificationUrl(UserModel $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
    }
}
