<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorDisableRule;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\TwoFactor\Events\RecoveryCodesRegenerated;
use App\Domain\TwoFactor\Events\TwoFactorDisabled;
use App\Domain\TwoFactor\Events\TwoFactorEnabled;
use App\Http\Middleware\BlockSiteWritesWhenImpersonatingReadOnly;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Tests\TestCase;

/**
 * Turning two-factor authentication on, renewing its recovery codes and turning it off from
 * «Ajustes de la cuenta → Seguridad» (core#769): the password and a code are asked every time, the
 * secret and the recovery codes travel only in the answer that creates them, and nothing changes
 * while an administrator looks at the account.
 */
final class ProfileTwoFactorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** A moment in the middle of a 30-second step */
    private const int NOW = 1_800_000_015;

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

    // Starting

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->postJson(route('profile.two-factor.begin'), ['current_password' => 'password'])->assertUnauthorized();
    }

    public function test_starting_with_a_wrong_password_answers_an_error_on_the_field_and_stores_nothing(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->postJson(route('profile.two-factor.begin'), ['current_password' => 'not-it'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertSame(0, UserTwoFactorModel::query()->count());
    }

    public function test_starting_without_a_password_is_refused(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->postJson(route('profile.two-factor.begin'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
    }

    public function test_starting_returns_the_key_and_the_qr_once_and_never_cached(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user)->postJson(route('profile.two-factor.begin'), ['current_password' => 'password']);

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $secret = $response->json('secret');
        $this->assertIsString($secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertSame(implode(' ', str_split($secret, 4)), $response->json('groupedSecret'));
        $this->assertStringStartsWith('<svg', (string) $response->json('qrCodeSvg'));
        // The otpauth address carries the secret too: only the picture and the key go to the browser
        $this->assertNull($response->json('otpauthUri'));
    }

    public function test_the_profile_page_never_carries_the_secret(): void
    {
        $user = UserModel::factory()->create();
        $secret = $this->actingAs($user)
            ->postJson(route('profile.two-factor.begin'), ['current_password' => 'password'])
            ->json('secret');

        $page = $this->get(route('profile.show', ['tab' => 'seguridad']));

        $page->assertOk();
        $this->assertStringNotContainsString((string) $secret, $page->getContent() ?: '');
        $page->assertInertia(fn (Assert $inertia) => $inertia
            ->where('security.twoFactor.active', false)
            ->where('security.twoFactor.since', null)
            ->where('security.twoFactor.remainingCodes', 0));
    }

    public function test_starting_while_it_is_already_on_answers_a_conflict(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);

        $this->asSealed($user)
            ->postJson(route('profile.two-factor.begin'), ['current_password' => 'password'])
            ->assertConflict();
    }

    // Confirming

    public function test_confirming_with_the_right_code_returns_eight_recovery_codes_once_and_keeps_the_session(): void
    {
        Event::fake([TwoFactorEnabled::class]);
        $user = UserModel::factory()->create();
        $secret = $this->begin($user);

        $response = $this->postJson(route('profile.two-factor.confirm'), ['code' => $this->code($secret)]);

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $codes = $response->json('recoveryCodes');
        $this->assertIsArray($codes);
        $this->assertCount(8, $codes);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{5}-[a-z0-9]{5}$/', (string) $codes[0]);
        Event::assertDispatched(TwoFactorEnabled::class);
        // The session that turned it on is the one kept: the next request still signs in (the account
        // is read again, as every real request does, to see the seal it holds now)
        $this->actingAs($this->fresh($user))->get(route('profile.show'))->assertOk();

        $page = $this->get(route('profile.show', ['tab' => 'seguridad']));
        $this->assertStringNotContainsString((string) $codes[0], $page->getContent() ?: '');
        $page->assertInertia(fn (Assert $inertia) => $inertia
            ->where('security.twoFactor.active', true)
            ->where('security.twoFactor.remainingCodes', 8)
            ->where('security.twoFactor.canDisable', true)
            ->whereType('security.twoFactor.since', 'string'));
    }

    public function test_confirming_with_a_wrong_code_answers_an_error_on_the_field(): void
    {
        $user = UserModel::factory()->create();
        $this->begin($user);

        $this->postJson(route('profile.two-factor.confirm'), ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertFalse(app(TwoFactorServiceInterface::class)->isEnabled((string) $user->id));
    }

    public function test_confirming_without_starting_answers_a_conflict(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->postJson(route('profile.two-factor.confirm'), ['code' => '123456'])
            ->assertConflict();
    }

    public function test_confirming_accepts_the_code_with_a_space_in_the_middle(): void
    {
        $user = UserModel::factory()->create();
        $secret = $this->begin($user);
        $code = $this->code($secret);

        $this->postJson(route('profile.two-factor.confirm'), ['code' => substr($code, 0, 3).' '.substr($code, 3)])->assertOk();
    }

    // New recovery codes

    public function test_new_recovery_codes_need_the_password_and_a_code(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $this->asSealed($user);

        $this->postJson(route('profile.two-factor.recovery-codes'), ['current_password' => 'not-it', 'code' => $this->code($this->secretOf($user), 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
        $this->postJson(route('profile.two-factor.recovery-codes'), ['current_password' => 'password', 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_new_recovery_codes_replace_the_old_ones(): void
    {
        Event::fake([RecoveryCodesRegenerated::class]);
        $user = UserModel::factory()->create();
        $old = $this->enable($user);
        $this->asSealed($user);

        $response = $this->postJson(route('profile.two-factor.recovery-codes'), [
            'current_password' => 'password',
            'code' => $this->code($this->secretOf($user), 1),
        ]);

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $new = $response->json('recoveryCodes');
        $this->assertIsArray($new);
        $this->assertCount(8, $new);
        $this->assertSame([], array_intersect($old, $new));
        $service = app(TwoFactorServiceInterface::class);
        $this->assertFalse($service->useRecoveryCode((string) $user->id, $old[0]));
        $this->assertTrue($service->useRecoveryCode((string) $user->id, $new[0]));
        Event::assertDispatched(RecoveryCodesRegenerated::class);
    }

    public function test_a_code_already_used_is_refused_the_second_time(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $this->asSealed($user);
        $code = $this->code($this->secretOf($user), 1);
        $payload = ['current_password' => 'password', 'code' => $code];

        $this->postJson(route('profile.two-factor.recovery-codes'), $payload)->assertOk();
        $this->postJson(route('profile.two-factor.recovery-codes'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_the_code_that_confirmed_it_cannot_be_used_again_to_change_anything(): void
    {
        $user = UserModel::factory()->create();
        $secret = $this->begin($user);
        $code = $this->code($secret);
        $this->postJson(route('profile.two-factor.confirm'), ['code' => $code])->assertOk();

        $this->actingAs($this->fresh($user))->deleteJson(route('profile.two-factor.disable'), ['current_password' => 'password', 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_recovery_code_also_authorises_new_ones_and_is_used_up(): void
    {
        $user = UserModel::factory()->create();
        $codes = $this->enable($user);
        $this->asSealed($user);

        $this->postJson(route('profile.two-factor.recovery-codes'), ['current_password' => 'password', 'code' => $codes[0]])->assertOk();

        $this->assertFalse(app(TwoFactorServiceInterface::class)->useRecoveryCode((string) $user->id, $codes[0]));
    }

    public function test_new_recovery_codes_without_it_turned_on_answer_a_conflict(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->postJson(route('profile.two-factor.recovery-codes'), ['current_password' => 'password', 'code' => '123456'])
            ->assertConflict();
    }

    // Turning it off

    public function test_turning_it_off_needs_the_password_and_a_code(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $this->asSealed($user);

        $this->deleteJson(route('profile.two-factor.disable'), ['current_password' => 'not-it', 'code' => $this->code($this->secretOf($user), 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
        $this->deleteJson(route('profile.two-factor.disable'), ['current_password' => 'password', 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertTrue(app(TwoFactorServiceInterface::class)->isEnabled((string) $user->id));
    }

    public function test_turning_it_off_deletes_the_secret_and_keeps_the_session(): void
    {
        Event::fake([TwoFactorDisabled::class]);
        $user = UserModel::factory()->create();
        $this->enable($user);
        $this->asSealed($user);

        $this->deleteJson(route('profile.two-factor.disable'), [
            'current_password' => 'password',
            'code' => $this->code($this->secretOf($user), 1),
        ])->assertNoContent();

        $this->assertSame(0, UserTwoFactorModel::query()->count());
        Event::assertDispatched(TwoFactorDisabled::class);
        $this->actingAs($this->fresh($user))->get(route('profile.show'))->assertOk();
    }

    public function test_turning_it_off_is_refused_when_the_account_cannot_turn_it_off(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $rule = Mockery::mock(TwoFactorDisableRule::class);
        $rule->shouldReceive('allowed')->andReturn(false);
        $this->app->instance(TwoFactorDisableRule::class, $rule);
        $this->asSealed($user);

        $this->deleteJson(route('profile.two-factor.disable'), [
            'current_password' => 'password',
            'code' => $this->code($this->secretOf($user), 1),
        ])->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertTrue(app(TwoFactorServiceInterface::class)->isEnabled((string) $user->id));
        $this->get(route('profile.show', ['tab' => 'seguridad']))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('security.twoFactor.canDisable', false));
    }

    public function test_turning_it_off_is_refused_while_a_role_held_requires_it(): void
    {
        $user = UserModel::factory()->create();
        $user->roles()->attach(RoleModel::query()->create(['name' => 'tesoreria', 'display_name' => 'Tesorería', 'requires_two_factor' => true])->id);
        $this->enable($user);
        $this->asSealed($user);

        $this->deleteJson(route('profile.two-factor.disable'), [
            'current_password' => 'password',
            'code' => $this->code($this->secretOf($user), 1),
        ])->assertForbidden()->assertJsonPath('message', __('two_factor.profile.cannot_disable'));

        $this->assertTrue(app(TwoFactorServiceInterface::class)->isEnabled((string) $user->id));
        $this->get(route('profile.show', ['tab' => 'seguridad']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('security.twoFactor.canDisable', false)
                ->where('security.twoFactor.requiredBy', ['Tesorería']));
    }

    public function test_a_role_paused_for_membership_still_keeps_it_on(): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $user = UserModel::factory()->create();
        $user->roles()->attach(RoleModel::query()->create([
            'name' => 'tesoreria',
            'display_name' => 'Tesorería',
            'requires_two_factor' => true,
            'requires_association_member' => true,
        ])->id);
        $this->enable($user);

        $this->assertFalse(app(TwoFactorDisableRule::class)->allowed((string) $user->id));
    }

    public function test_the_disable_rule_refuses_when_the_roles_cannot_be_read(): void
    {
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('getRoleNamesForUser')->willThrowException(new RuntimeException('database down'));
        $rule = new TwoFactorDisableRule($roles);

        $this->assertFalse($rule->allowed('any-account'));
    }

    public function test_the_security_tab_refuses_and_names_none_when_the_roles_cannot_be_read(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('getRoleNamesForUser')->willThrowException(new RuntimeException('database down'));
        $this->app->instance(TwoFactorDisableRule::class, new TwoFactorDisableRule($roles));
        $this->asSealed($user);

        $this->get(route('profile.show', ['tab' => 'seguridad']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('security.twoFactor.canDisable', false)
                ->where('security.twoFactor.requiredBy', []));
    }

    public function test_the_security_tab_names_the_roles_that_require_it_while_it_is_off(): void
    {
        $user = UserModel::factory()->create();
        $user->roles()->attach(RoleModel::query()->create(['name' => 'tesoreria', 'display_name' => 'Tesorería', 'requires_two_factor' => true])->id);

        $this->actingAs($user)->get(route('profile.show', ['tab' => 'seguridad']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('security.twoFactor.active', false)
                ->where('security.twoFactor.requiredBy', ['Tesorería']));
    }

    public function test_without_such_roles_it_can_be_turned_off_and_names_none(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $this->asSealed($user);

        $this->get(route('profile.show', ['tab' => 'seguridad']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('security.twoFactor.canDisable', true)
                ->where('security.twoFactor.requiredBy', []));
    }

    // Changing the app

    public function test_changing_app_returns_a_new_key_and_keeps_the_current_secret(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $old = $this->secretOf($user);
        $this->asSealed($user);

        $response = $this->postJson(route('profile.two-factor.app.begin'), [
            'current_password' => 'password',
            'code' => $this->code($old, 1),
        ]);

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', (string) $response->json('secret'));
        $this->assertNotSame($old, $response->json('secret'));
        $this->assertStringStartsWith('<svg', (string) $response->json('qrCodeSvg'));
        $this->assertSame($old, $this->secretOf($user));
    }

    public function test_changing_app_needs_the_password_and_a_code_of_the_factor_in_use(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $old = $this->secretOf($user);
        $this->asSealed($user);

        $this->postJson(route('profile.two-factor.app.begin'), ['current_password' => 'not-it', 'code' => $this->code($old, 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
        $this->postJson(route('profile.two-factor.app.begin'), ['current_password' => 'password', 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        // The password alone is not enough: nothing was started
        $this->postJson(route('profile.two-factor.app.begin'), ['current_password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->assertNull(UserTwoFactorModel::query()->findOrFail($user->id)->pending_secret);
    }

    public function test_changing_app_accepts_a_recovery_code_as_the_factor_in_use(): void
    {
        $user = UserModel::factory()->create();
        $codes = $this->enable($user);
        $this->asSealed($user);

        $this->postJson(route('profile.two-factor.app.begin'), ['current_password' => 'password', 'code' => $codes[0]])->assertOk();

        $this->assertFalse(app(TwoFactorServiceInterface::class)->useRecoveryCode((string) $user->id, $codes[0]));
    }

    public function test_changing_app_without_it_turned_on_answers_a_conflict(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->postJson(route('profile.two-factor.app.begin'), ['current_password' => 'password', 'code' => '123456'])->assertConflict();
        $this->postJson(route('profile.two-factor.app.confirm'), ['code' => '123456'])->assertConflict();
    }

    public function test_confirming_the_new_app_swaps_the_secret_keeps_the_codes_and_this_session_only(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $old = $this->secretOf($user);
        $sealBefore = (string) $this->fresh($user)->session_seal;
        $this->asSealed($user);
        $new = (string) $this->postJson(route('profile.two-factor.app.begin'), ['current_password' => 'password', 'code' => $this->code($old, 1)])->json('secret');

        // The new app shows the same step's code as the one just typed: it is accepted all the same
        $this->postJson(route('profile.two-factor.app.confirm'), ['code' => $this->code($new, 1)])->assertNoContent();

        $this->assertSame($new, $this->secretOf($user));
        $this->assertNotSame($sealBefore, (string) $this->fresh($user)->session_seal);
        // Another session of the account (holding the old seal) is signed out
        $this->actingAs($this->fresh($user))
            ->withSession([TwoFactorSessionSeal::SESSION_KEY => $sealBefore])
            ->get(route('profile.show'))
            ->assertRedirect(route('login'));
    }

    public function test_the_session_that_changed_the_app_stays_signed_in_with_its_recovery_codes(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $old = $this->secretOf($user);
        $this->asSealed($user);
        $new = (string) $this->postJson(route('profile.two-factor.app.begin'), ['current_password' => 'password', 'code' => $this->code($old, 1)])->json('secret');
        $this->postJson(route('profile.two-factor.app.confirm'), ['code' => $this->code($new, 1)])->assertNoContent();

        $this->actingAs($this->fresh($user))
            ->get(route('profile.show', ['tab' => 'seguridad']))
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia->where('security.twoFactor.remainingCodes', 8));
    }

    public function test_confirming_the_new_app_with_a_wrong_code_or_one_of_the_old_app_is_refused(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $old = $this->secretOf($user);
        $this->asSealed($user);
        $this->postJson(route('profile.two-factor.app.begin'), ['current_password' => 'password', 'code' => $this->code($old, 1)])->assertOk();

        $this->postJson(route('profile.two-factor.app.confirm'), ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->postJson(route('profile.two-factor.app.confirm'), ['code' => $this->code($old, 0)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertSame($old, $this->secretOf($user));
    }

    // Limits, verification and impersonation

    public function test_every_account_has_its_own_limit_of_six_attempts_a_minute(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson(route('profile.two-factor.begin'), ['current_password' => 'not-it'])->assertUnprocessable();
        }

        $this->postJson(route('profile.two-factor.begin'), ['current_password' => 'password'])->assertTooManyRequests();
        // Another account is not held back by it
        $this->actingAs(UserModel::factory()->create())
            ->postJson(route('profile.two-factor.begin'), ['current_password' => 'not-it'])
            ->assertUnprocessable();
    }

    public function test_an_account_that_must_verify_its_email_is_sent_to_the_notice(): void
    {
        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');

        $this->actingAs(UserModel::factory()->unverified()->create())
            ->postJson(route('profile.two-factor.begin'), ['current_password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('error', 'email_verification_required');
    }

    public function test_a_read_only_impersonation_refuses_every_route(): void
    {
        $target = UserModel::factory()->create();
        $this->enable($target);
        $this->startImpersonation($target, ImpersonationMode::ReadOnly);

        foreach ([
            ['postJson', 'profile.two-factor.begin'],
            ['postJson', 'profile.two-factor.confirm'],
            ['postJson', 'profile.two-factor.recovery-codes'],
            ['deleteJson', 'profile.two-factor.disable'],
            ['postJson', 'profile.two-factor.app.begin'],
            ['postJson', 'profile.two-factor.app.confirm'],
        ] as [$method, $route]) {
            $this->{$method}(route($route), ['current_password' => 'password', 'code' => '123456'])
                ->assertForbidden()
                ->assertJsonPath('error', BlockSiteWritesWhenImpersonatingReadOnly::JSON_ERROR_CODE);
        }

        $this->assertSame(1, UserTwoFactorModel::query()->count());
    }

    public function test_a_simulated_impersonation_refuses_every_action_and_changes_nothing(): void
    {
        $target = UserModel::factory()->create();
        $this->enable($target);
        $secret = $this->secretOf($target);
        $this->startImpersonation($target, ImpersonationMode::Simulate);

        foreach ([
            ['postJson', 'profile.two-factor.begin'],
            ['postJson', 'profile.two-factor.confirm'],
            ['postJson', 'profile.two-factor.recovery-codes'],
            ['deleteJson', 'profile.two-factor.disable'],
            ['postJson', 'profile.two-factor.app.begin'],
            ['postJson', 'profile.two-factor.app.confirm'],
        ] as [$method, $route]) {
            $this->{$method}(route($route), ['current_password' => 'password', 'code' => $this->code($secret, 1)])
                ->assertForbidden()
                ->assertJsonPath('reason', 'impersonating');
        }

        // Nothing was spent or changed: the same code still works for the account itself
        $service = app(TwoFactorServiceInterface::class);
        $this->assertTrue($service->isEnabled((string) $target->id));
        $this->assertSame($secret, $this->secretOf($target));
        $this->assertSame(8, $service->status((string) $target->id)->recoveryCodesRemaining);
        $this->assertNull(UserTwoFactorModel::query()->findOrFail($target->id)->pending_secret);
        $this->assertTrue($service->verify((string) $target->id, $this->code($secret, 1)));
    }

    public function test_a_wrong_password_does_not_spend_the_code(): void
    {
        $user = UserModel::factory()->create();
        $codes = $this->enable($user);
        $secret = $this->secretOf($user);
        $this->asSealed($user);
        $code = $this->code($secret, 1);

        // One wrong password on each route, with a code of the app or a recovery code
        foreach ([
            ['postJson', 'profile.two-factor.recovery-codes', $code],
            ['deleteJson', 'profile.two-factor.disable', $codes[0]],
            ['postJson', 'profile.two-factor.app.begin', $code],
        ] as [$method, $route, $typed]) {
            $this->{$method}(route($route), ['current_password' => 'not-it', 'code' => $typed])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('current_password');
        }

        // Neither the code of the app nor the recovery code was used up
        $service = app(TwoFactorServiceInterface::class);
        $this->assertSame(8, $service->status((string) $user->id)->recoveryCodesRemaining);
        $this->postJson(route('profile.two-factor.app.begin'), ['current_password' => 'password', 'code' => $code])->assertOk();
        $this->assertTrue($service->useRecoveryCode((string) $user->id, $codes[0]));
    }

    public function test_the_code_is_not_kept_in_the_session_input_when_the_password_is_wrong(): void
    {
        $user = UserModel::factory()->create();
        $codes = $this->enable($user);
        $this->asSealed($user);

        // A form post (not JSON) goes back with its input flashed, apart from what must not be kept
        $this->post(route('profile.two-factor.recovery-codes'), ['current_password' => 'not-it', 'code' => $codes[0]])
            ->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.code')
            ->assertSessionMissing('_old_input.current_password');
    }

    public function test_the_text_of_a_wrong_code_depends_on_whether_a_recovery_code_would_do(): void
    {
        $user = UserModel::factory()->create();
        $secret = $this->begin($user);

        // Confirming the first code of an app: a recovery code is not an option
        $confirm = $this->postJson(route('profile.two-factor.confirm'), ['code' => '000000'])->assertUnprocessable();
        $this->assertStringNotContainsString('recuperación', (string) $confirm->json('errors.code.0'));
        $this->postJson(route('profile.two-factor.confirm'), ['code' => $this->code($secret)])->assertOk();

        // Proving the factor in use: it is
        $this->actingAs($this->fresh($user));
        $prove = $this->deleteJson(route('profile.two-factor.disable'), ['current_password' => 'password', 'code' => '000000'])->assertUnprocessable();
        $this->assertStringContainsString('recuperación', (string) $prove->json('errors.code.0'));
    }

    // The tab's prop

    public function test_the_prop_counts_the_recovery_codes_left(): void
    {
        $user = UserModel::factory()->create();
        $codes = $this->enable($user);
        app(TwoFactorServiceInterface::class)->useRecoveryCode((string) $user->id, $codes[0]);

        $this->asSealed($user)
            ->get(route('profile.show', ['tab' => 'seguridad']))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('security.twoFactor.remainingCodes', 7));
    }

    /**
     * Starts setting it up as the signed-in account and returns the secret.
     */
    private function begin(UserModel $user): string
    {
        $response = $this->actingAs($user)->postJson(route('profile.two-factor.begin'), ['current_password' => 'password']);
        $response->assertOk();

        return (string) $response->json('secret');
    }

    /**
     * Turns it on through the service, one step back so that codes of the next step are still
     * accepted; returns the recovery codes.
     *
     * @return list<string>
     */
    private function enable(UserModel $user): array
    {
        $service = app(TwoFactorServiceInterface::class);
        $setup = $service->beginSetup((string) $user->id);

        return $service->confirmSetup((string) $user->id, $this->code($setup->secret));
    }

    private function code(string $secret, int $stepOffset = 0): string
    {
        return (new Google2FA)->oathTotp($secret, intdiv(self::NOW, 30) + $stepOffset);
    }

    private function secretOf(UserModel $user): string
    {
        $row = UserTwoFactorModel::query()->findOrFail($user->id);

        return $row->secret;
    }

    /**
     * Signs the account in the way a real sign-in leaves the session: holding the seal, which an
     * account with two-factor authentication needs to stay signed in.
     */
    private function asSealed(UserModel $user): static
    {
        return $this->actingAs($this->fresh($user))
            ->withSession([TwoFactorSessionSeal::SESSION_KEY => (string) $this->fresh($user)->session_seal]);
    }

    private function fresh(UserModel $user): UserModel
    {
        $fresh = $user->fresh();
        $this->assertNotNull($fresh);

        return $fresh;
    }

    private function startImpersonation(UserModel $target, ImpersonationMode $mode): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);
        app(ImpersonationServiceInterface::class)->start($this->fresh($admin), $target, $mode);
    }
}
