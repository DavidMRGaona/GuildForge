<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use App\Http\Middleware\HandleInertiaRequests;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * VerificationBanner resends the link with a partial reload (`only: ['auth']`) so the page it is on
 * does not also show the resend flash: the banner shows its own feedback. The flash is consumed
 * by that reload, so it never shows up later either. The notice page's own resend is a full visit
 * and keeps receiving the flash.
 */
final class VerificationBannerResendTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
        Notification::fake();
    }

    public function test_the_banner_resend_sends_the_link_without_a_flash_for_the_page(): void
    {
        $user = UserModel::factory()->unverified()->create();
        $this->actingAs($user);

        $this->withHeaders($this->inertiaHeaders())
            ->from(route('profile.show'))
            ->post(route('verification.send'))
            ->assertRedirect(route('profile.show'));

        // The follow-up GET of the redirect, as Inertia sends it for `only: ['auth']`
        $this->withHeaders([
            ...$this->inertiaHeaders(),
            'X-Inertia-Partial-Component' => 'Profile/Show',
            'X-Inertia-Partial-Data' => 'auth',
        ])
            ->get(route('profile.show'))
            ->assertOk()
            ->assertJsonPath('props.auth.user.mustVerifyEmail', true)
            ->assertJsonMissingPath('props.flash');

        Notification::assertSentTo($user, VerifyEmail::class);

        // Consumed: the next visit does not show it either
        $this->flushHeaders()
            ->get(route('profile.show'))
            ->assertInertia(fn (Assert $page) => $page->where('flash.success', null));
    }

    public function test_the_notice_page_resend_still_gets_its_flash(): void
    {
        $this->actingAs(UserModel::factory()->unverified()->create());

        $this->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'));

        $this->get(route('verification.notice'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/VerifyEmail')
                ->where('flash.success', __('auth.verification_link_sent')));
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
