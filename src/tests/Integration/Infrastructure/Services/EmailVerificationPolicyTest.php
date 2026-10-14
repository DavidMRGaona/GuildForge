<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\Services\EmailVerificationPolicyInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use stdClass;
use Tests\TestCase;

final class EmailVerificationPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string CUTOFF = '2026-10-14T10:00:00+00:00';

    private SettingsServiceInterface $settings;

    private EmailVerificationPolicyInterface $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(SettingsServiceInterface::class);
        $this->settings->set('email_verification_enforced_since', self::CUTOFF);
        $this->policy = app(EmailVerificationPolicyInterface::class);
    }

    public function test_nobody_must_verify_when_the_setting_is_off(): void
    {
        $this->settings->set('auth_email_verification_required', '0');

        $this->assertFalse($this->policy->mustVerify($this->userCreatedAt('2026-10-15 12:00:00')));
        $this->assertFalse($this->policy->mustVerify(new stdClass));
    }

    public function test_an_unverified_account_created_after_the_cutoff_must_verify(): void
    {
        $this->enable();

        $this->assertTrue($this->policy->mustVerify($this->userCreatedAt('2026-10-15 12:00:00')));
    }

    public function test_an_account_created_exactly_at_the_cutoff_must_verify(): void
    {
        $this->enable();

        $this->assertTrue($this->policy->mustVerify($this->userCreatedAt(self::CUTOFF)));
    }

    public function test_an_unverified_account_created_before_the_cutoff_keeps_working(): void
    {
        $this->enable();

        $this->assertFalse($this->policy->mustVerify($this->userCreatedAt('2026-10-14T09:59:59+00:00')));
    }

    public function test_the_cutoff_is_compared_as_an_instant_whatever_the_timezone(): void
    {
        $this->enable();

        // 11:30 in Madrid (UTC+2 in October) is 09:30 UTC: before the 10:00 UTC cutoff
        $this->assertFalse($this->policy->mustVerify($this->userCreatedAt('2026-10-14 11:30:00', 'Europe/Madrid')));
        // 12:30 in Madrid is 10:30 UTC: after it
        $this->assertTrue($this->policy->mustVerify($this->userCreatedAt('2026-10-14 12:30:00', 'Europe/Madrid')));
    }

    public function test_a_verified_account_never_has_to_verify(): void
    {
        $this->enable();
        $user = $this->userCreatedAt('2026-10-15 12:00:00');
        $user->email_verified_at = Carbon::parse('2026-10-15 12:05:00');

        $this->assertFalse($this->policy->mustVerify($user));
    }

    public function test_an_anonymized_account_never_has_to_verify(): void
    {
        $this->enable();
        $user = $this->userCreatedAt('2026-10-15 12:00:00');
        $user->anonymized_at = Carbon::parse('2026-10-16 08:00:00');

        $this->assertFalse($this->policy->mustVerify($user));
    }

    public function test_every_unverified_account_must_verify_when_the_cutoff_is_missing(): void
    {
        $this->enable();
        $this->settings->set('email_verification_enforced_since', '');

        $this->assertTrue($this->policy->mustVerify($this->userCreatedAt('2020-01-01 00:00:00')));
    }

    public function test_every_unverified_account_must_verify_when_the_cutoff_is_malformed(): void
    {
        $this->enable();
        $this->settings->set('email_verification_enforced_since', 'not a date');

        $this->assertTrue($this->policy->mustVerify($this->userCreatedAt('2020-01-01 00:00:00')));
    }

    public function test_an_unverified_account_without_creation_date_must_verify(): void
    {
        $this->enable();
        $user = UserModel::factory()->unverified()->make();
        $user->created_at = null;

        $this->assertTrue($this->policy->mustVerify($user));
    }

    public function test_an_unknown_user_object_must_verify_when_the_setting_is_on(): void
    {
        $this->enable();

        $this->assertTrue($this->policy->mustVerify(new stdClass));
    }

    private function enable(): void
    {
        $this->settings->set('auth_email_verification_required', '1');
    }

    private function userCreatedAt(string $createdAt, ?string $timezone = null): UserModel
    {
        $user = UserModel::factory()->unverified()->make();
        // Timestamps are stored without offset, in the application timezone
        $user->created_at = Carbon::parse($createdAt, $timezone)->setTimezone((string) config('app.timezone'));

        return $user;
    }
}
