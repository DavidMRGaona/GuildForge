<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Services\EmailVerificationPolicyInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/**
 * What the frontend is told about the signed-in account's email verification: the banner in
 * DefaultLayout and useVerificationGate() in modules read auth.user.mustVerifyEmail (the same
 * EmailVerificationPolicyInterface decision EnsureEmailIsVerifiedWhenRequired applies) and
 * auth.user.verificationEmail (where the verification link goes).
 */
final class SharedEmailVerificationStateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string CUTOFF = '2026-01-01T00:00:00+00:00';

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', self::CUTOFF);
    }

    public function test_a_guest_gets_no_user_so_nothing_to_verify(): void
    {
        $this->requireVerification();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }

    public function test_an_unverified_account_need_not_verify_when_the_setting_is_off(): void
    {
        $this->assertFalse($this->sharedUser($this->newUnverifiedUser())['mustVerifyEmail']);
    }

    public function test_a_verified_account_need_not_verify(): void
    {
        $this->requireVerification();

        $this->assertFalse($this->sharedUser(UserModel::factory()->create())['mustVerifyEmail']);
    }

    public function test_an_unverified_account_created_before_the_cutoff_need_not_verify(): void
    {
        $this->requireVerification();
        $user = UserModel::factory()->unverified()->create(['created_at' => '2025-06-01 12:00:00']);

        $this->assertFalse($this->sharedUser($user)['mustVerifyEmail']);
    }

    public function test_an_anonymized_account_need_not_verify(): void
    {
        $this->requireVerification();
        $user = UserModel::factory()->unverified()->create(['anonymized_at' => now()]);

        $this->assertFalse($this->sharedUser($user)['mustVerifyEmail']);
    }

    public function test_a_new_unverified_account_must_verify_and_the_link_goes_to_its_email(): void
    {
        $this->requireVerification();
        $user = UserModel::factory()->unverified()->create(['email' => 'nueva@example.com']);

        $shared = $this->sharedUser($user);

        $this->assertTrue($shared['mustVerifyEmail']);
        $this->assertSame('nueva@example.com', $shared['verificationEmail']);
    }

    public function test_with_an_email_change_pending_the_link_goes_to_the_new_address(): void
    {
        $this->requireVerification();
        $user = UserModel::factory()->unverified()->create([
            'email' => 'typo@exmaple.com',
            'pending_email' => 'corrected@example.com',
        ]);

        $shared = $this->sharedUser($user);

        $this->assertTrue($shared['mustVerifyEmail']);
        $this->assertSame('corrected@example.com', $shared['verificationEmail']);
        $this->assertSame('typo@exmaple.com', $shared['email']);
    }

    public function test_an_account_that_need_not_verify_still_gets_where_a_link_would_go(): void
    {
        $shared = $this->sharedUser(UserModel::factory()->create(['email' => 'socio@example.com']));

        $this->assertFalse($shared['mustVerifyEmail']);
        $this->assertSame('socio@example.com', $shared['verificationEmail']);
    }

    public function test_the_user_is_still_shared_when_the_policy_fails_and_an_unverified_account_must_verify(): void
    {
        Log::spy();
        $this->failingPolicy();

        $shared = $this->sharedUser($this->newUnverifiedUser());

        $this->assertTrue($shared['mustVerifyEmail']);
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message): bool => str_contains($message, 'mustVerifyEmail'),
        );
    }

    public function test_a_verified_account_is_never_told_to_verify_when_the_policy_fails(): void
    {
        $this->failingPolicy();

        $this->assertFalse($this->sharedUser(UserModel::factory()->create())['mustVerifyEmail']);
    }

    private function requireVerification(): void
    {
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
    }

    private function newUnverifiedUser(): UserModel
    {
        return UserModel::factory()->unverified()->create();
    }

    private function failingPolicy(): void
    {
        $this->app->instance(EmailVerificationPolicyInterface::class, new class implements EmailVerificationPolicyInterface
        {
            public function mustVerify(object $user): bool
            {
                throw new RuntimeException('Settings store unreachable');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedUser(UserModel $model): array
    {
        $shared = null;

        $this->actingAs($model)
            ->get('/')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$shared): void {
                $shared = $page->toArray()['props']['auth']['user'];
            });

        $this->assertIsArray($shared);
        $this->assertSame($model->id, $shared['id']);
        $this->assertArrayHasKey('mustVerifyEmail', $shared);
        $this->assertIsBool($shared['mustVerifyEmail']);
        $this->assertArrayHasKey('verificationEmail', $shared);

        /** @var array<string, mixed> $shared */
        return $shared;
    }
}
