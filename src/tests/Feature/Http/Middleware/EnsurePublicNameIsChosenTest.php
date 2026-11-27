<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Http\Middleware\EnsurePublicNameIsChosen;
use App\Http\Middleware\HandleInertiaRequests;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * An account without a public name chooses one, once, before acting as itself on the site
 * (core#855): routes with `auth` send it to the screen, the rest of the site stays as it was.
 */
final class EnsurePublicNameIsChosenTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string PROTECTED_URL = '/_test/public-name/protected';

    private const string PUBLIC_URL = '/_test/public-name/public';

    protected function setUp(): void
    {
        parent::setUp();

        // Registered the way ModuleServiceProvider::loadRoutes() registers module routes
        Route::middleware('web')->group(function (): void {
            Route::middleware('auth')->group(function (): void {
                Route::get(self::PROTECTED_URL, static fn (): string => 'protected');
                Route::post(self::PROTECTED_URL, static fn (): string => 'protected');
            });
            Route::get(self::PUBLIC_URL, static fn (): string => 'public');
        });
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_an_account_without_a_public_name_is_sent_to_the_screen(): void
    {
        $this->actingAs($this->namelessUser());

        $this->get(self::PROTECTED_URL)->assertRedirect(route('public-name.show'));
        $this->get(route('profile.show'))->assertRedirect(route('public-name.show'));
    }

    public function test_a_blank_public_name_counts_as_none(): void
    {
        $this->actingAs(UserModel::factory()->create(['display_name' => '   ']));

        $this->get(self::PROTECTED_URL)->assertRedirect(route('public-name.show'));
    }

    public function test_the_page_asked_for_is_remembered_for_after_choosing(): void
    {
        $this->actingAs($this->namelessUser());

        $this->get(self::PROTECTED_URL);

        $this->assertSame(url(self::PROTECTED_URL), session('url.intended'));
    }

    public function test_a_form_sent_from_another_page_is_not_remembered_as_the_page_asked_for(): void
    {
        $this->actingAs($this->namelessUser());

        $this->post(self::PROTECTED_URL)->assertRedirect(route('public-name.show'));

        $this->assertNull(session('url.intended'));
    }

    public function test_an_inertia_visit_is_sent_to_the_screen(): void
    {
        $this->actingAs($this->namelessUser());

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        ])->get(self::PROTECTED_URL)->assertRedirect(route('public-name.show'));
    }

    public function test_a_json_request_gets_a_403_with_its_own_code_and_header(): void
    {
        $this->actingAs($this->namelessUser());

        $this->getJson(self::PROTECTED_URL)
            ->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('error', EnsurePublicNameIsChosen::JSON_ERROR_CODE)
            ->assertJsonPath('message', __('auth.public_name.required'))
            ->assertHeader(EnsurePublicNameIsChosen::JSON_ERROR_HEADER, '1');
    }

    public function test_an_account_with_a_public_name_is_never_stopped(): void
    {
        $this->actingAs(UserModel::factory()->create(['display_name' => 'Ana']));

        $this->getJson(self::PROTECTED_URL)
            ->assertOk()
            ->assertHeaderMissing(EnsurePublicNameIsChosen::JSON_ERROR_HEADER);
    }

    public function test_guests_are_left_to_the_auth_middleware(): void
    {
        $this->get(self::PROTECTED_URL)->assertRedirect(route('login'));
    }

    public function test_public_pages_stay_open(): void
    {
        $this->actingAs($this->namelessUser());

        $this->get(self::PUBLIC_URL)->assertOk()->assertSee('public');
        $this->get('/')->assertOk();
    }

    public function test_the_screen_itself_stays_reachable(): void
    {
        $this->actingAs($this->namelessUser());

        $this->get(route('public-name.show'))->assertOk();
    }

    public function test_logging_out_stays_possible(): void
    {
        $this->actingAs($this->namelessUser());

        $this->post(route('logout'))->assertRedirect();

        $this->assertGuest();
    }

    public function test_two_factor_management_stays_reachable(): void
    {
        $this->actingAs($this->namelessUser());

        // Not sent to the screen: the controller answers (here, the password is missing)
        $this->postJson(route('profile.two-factor.begin'))
            ->assertHeaderMissing(EnsurePublicNameIsChosen::JSON_ERROR_HEADER)
            ->assertUnprocessable();
    }

    public function test_the_email_is_verified_first(): void
    {
        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
        $this->actingAs(UserModel::factory()->unverified()->create(['display_name' => null]));

        $this->get(self::PROTECTED_URL)->assertRedirect(route('verification.notice'));
        // The email can still be corrected from the settings
        $this->get(route('profile.show'))->assertOk();
        $this->get(route('verification.notice'))->assertOk();
    }

    public function test_an_impersonation_never_sees_the_screen(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);
        app(ImpersonationServiceInterface::class)->start($admin, $this->namelessUser(), ImpersonationMode::ReadOnly);

        $this->get(self::PROTECTED_URL)->assertOk()->assertSee('protected');
    }

    public function test_once_chosen_the_screen_is_never_shown_again(): void
    {
        $user = $this->namelessUser();
        $this->actingAs($user);

        $this->post(route('public-name.store'), ['display_name' => 'Ana'])->assertRedirect();
        // Each request reads the account again; the test keeps the instance it signed in with
        $this->actingAs($user->refresh());

        $this->get(self::PROTECTED_URL)->assertOk();
        $this->get(route('public-name.show'))->assertRedirect(route('home'));
    }

    private function namelessUser(): UserModel
    {
        return UserModel::factory()->create(['name' => 'Ana Example Sample', 'display_name' => null]);
    }
}
