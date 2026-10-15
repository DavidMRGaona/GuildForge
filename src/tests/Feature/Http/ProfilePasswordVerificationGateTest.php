<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use App\Http\Middleware\HandleInertiaRequests;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * What ProfileAccountTab relies on to gate the change-password form: the profile page stays open
 * and tells it whether the account must verify (auth.user.mustVerifyEmail), and a password change
 * sent anyway by an account that must verify lands on the notice (the form then must not report
 * success), while every other account changes its password as before.
 */
final class ProfilePasswordVerificationGateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string CUTOFF = '2026-01-01T00:00:00+00:00';

    private const array NEW_PASSWORD = [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', self::CUTOFF);
    }

    public function test_the_profile_page_tells_an_account_that_must_verify_to_block_the_form(): void
    {
        $this->requireVerification();

        $this->actingAs(UserModel::factory()->unverified()->create())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('auth.user.mustVerifyEmail', true));
    }

    public function test_the_profile_page_leaves_the_form_open_to_accounts_that_need_not_verify(): void
    {
        $this->requireVerification();

        foreach ([
            UserModel::factory()->create(),
            UserModel::factory()->unverified()->create(['created_at' => '2025-06-01 12:00:00']),
        ] as $user) {
            $this->actingAs($user)
                ->get(route('profile.show'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('auth.user.mustVerifyEmail', false));
        }
    }

    public function test_a_password_change_sent_anyway_lands_on_the_notice_and_changes_nothing(): void
    {
        $this->requireVerification();
        $user = UserModel::factory()->unverified()->create();

        $this->actingAs($user)
            ->withHeaders($this->inertiaHeaders())
            ->from(route('profile.show'))
            ->put(route('profile.password'), self::NEW_PASSWORD)
            ->assertRedirect(route('verification.notice'));

        $this->assertTrue(Hash::check('password', (string) $user->fresh()?->password));
    }

    public function test_an_account_that_need_not_verify_changes_its_password_as_before(): void
    {
        $this->requireVerification();
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->withHeaders($this->inertiaHeaders())
            ->from(route('profile.show'))
            ->put(route('profile.password'), self::NEW_PASSWORD)
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', __('auth.password_changed'));

        $this->assertTrue(Hash::check('new-password-123', (string) $user->fresh()?->password));
    }

    private function requireVerification(): void
    {
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
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
