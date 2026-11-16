<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\TwoFactor;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Domain\TwoFactor\Events\RecoveryCodesRegenerated;
use App\Domain\TwoFactor\Events\RecoveryCodeUsed;
use App\Domain\TwoFactor\Events\TwoFactorDisabled;
use App\Domain\TwoFactor\Events\TwoFactorEnabled;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\Exceptions\TwoFactorUnavailableException;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * The two-factor service (core#768): secrets at rest, codes never accepted twice, recovery codes
 * used once, other sessions closed when it is turned on, and nothing done while impersonating.
 */
final class TwoFactorServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** A moment in the middle of a 30-second step */
    private const int NOW = 1_800_000_015;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));
        app(SettingsServiceInterface::class)->set('guild_name', 'Example [site]');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_setting_up_gives_a_key_a_uri_and_a_qr_code(): void
    {
        $user = UserModel::factory()->create(['email' => 'ana@example.org']);

        $setup = $this->service()->beginSetup((string) $user->id);

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $setup->secret);
        $this->assertSame(
            'otpauth://totp/Example%20%5Bsite%5D:ana%40example.org?secret='.$setup->secret.'&issuer=Example%20%5Bsite%5D&algorithm=SHA1&digits=6&period=30',
            $setup->otpauthUri,
        );
        $this->assertStringStartsWith('<svg', $setup->qrCodeSvg);
        $this->assertSame(substr($setup->secret, 0, 4).' '.substr($setup->secret, 4, 4), substr($setup->groupedSecret(), 0, 9));

        $status = $this->service()->status((string) $user->id);
        $this->assertFalse($status->enabled);
        $this->assertTrue($status->setupPending);
        $this->assertFalse($this->service()->isEnabled((string) $user->id));
    }

    public function test_the_secret_is_encrypted_at_rest_and_never_serialized(): void
    {
        $user = UserModel::factory()->create();
        $setup = $this->service()->beginSetup((string) $user->id);
        $codes = $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0));

        $raw = DB::table('user_two_factor')->where('user_id', $user->id)->first();
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString($setup->secret, (string) $raw->secret);
        $this->assertSame($setup->secret, Crypt::decryptString((string) $raw->secret));

        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, (string) $raw->recovery_codes);
            $this->assertStringNotContainsString(hash('sha256', $code), (string) $raw->recovery_codes);
            $this->assertStringNotContainsString($code, Crypt::decryptString((string) $raw->recovery_codes));
        }

        $model = UserTwoFactorModel::query()->findOrFail($user->id);
        $this->assertArrayNotHasKey('secret', $model->toArray());
        $this->assertArrayNotHasKey('recovery_codes', $model->toArray());
        $this->assertArrayNotHasKey('session_seal', $user->fresh()?->toArray() ?? []);
        $this->assertStringNotContainsString($setup->secret, (string) json_encode($user->fresh()));
    }

    public function test_a_new_setup_replaces_a_pending_one(): void
    {
        $user = UserModel::factory()->create();
        $first = $this->service()->beginSetup((string) $user->id);
        $second = $this->service()->beginSetup((string) $user->id);

        $this->assertNotSame($first->secret, $second->secret);
        $this->assertSame(1, UserTwoFactorModel::query()->count());

        $this->assertRefused(TwoFactorFailure::InvalidCode, fn () => $this->service()->confirmSetup((string) $user->id, $this->codeAt($first->secret, 0)));
        $this->service()->confirmSetup((string) $user->id, $this->codeAt($second->secret, 0));
        $this->assertTrue($this->service()->isEnabled((string) $user->id));
    }

    public function test_a_setup_never_replaces_one_in_use(): void
    {
        $user = UserModel::factory()->create();
        $secret = $this->enable($user);

        $this->assertRefused(TwoFactorFailure::AlreadyEnabled, fn () => $this->service()->beginSetup((string) $user->id));
        $this->assertSame($secret, UserTwoFactorModel::query()->findOrFail($user->id)->secret);
        $this->assertTrue($this->service()->isEnabled((string) $user->id));
    }

    public function test_an_unknown_account_cannot_set_it_up(): void
    {
        $this->assertRefused(TwoFactorFailure::UnknownAccount, fn () => $this->service()->beginSetup((string) Str::uuid7()));
        $this->assertFalse($this->service()->isEnabled((string) Str::uuid7()));
        $this->assertFalse($this->service()->isEnabled('not-a-uuid'));
    }

    public function test_confirming_needs_a_setup_and_a_valid_code(): void
    {
        $user = UserModel::factory()->create();

        $this->assertRefused(TwoFactorFailure::NoPendingSetup, fn () => $this->service()->confirmSetup((string) $user->id, '123456'));

        $setup = $this->service()->beginSetup((string) $user->id);
        $this->assertRefused(TwoFactorFailure::InvalidCode, fn () => $this->service()->confirmSetup((string) $user->id, 'abc'));
        $this->assertRefused(TwoFactorFailure::InvalidCode, fn () => $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 2)));
        $this->assertFalse($this->service()->isEnabled((string) $user->id));

        $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0));
        $this->assertRefused(TwoFactorFailure::AlreadyEnabled, fn () => $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 1)));
    }

    public function test_confirming_turns_it_on_and_shows_eight_recovery_codes_once(): void
    {
        Event::fake([TwoFactorEnabled::class]);
        $user = UserModel::factory()->create();
        $setup = $this->service()->beginSetup((string) $user->id);

        $codes = $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0));

        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[23456789a-hjkmnp-z]{5}-[23456789a-hjkmnp-z]{5}$/', $code);
        }

        $status = $this->service()->status((string) $user->id);
        $this->assertTrue($status->enabled);
        $this->assertFalse($status->setupPending);
        $this->assertSame(8, $status->recoveryCodesRemaining);
        $this->assertSame(self::NOW, $status->confirmedAt?->getTimestamp());
        Event::assertDispatched(TwoFactorEnabled::class, static fn (TwoFactorEnabled $event): bool => $event->userId === (string) $user->id);
    }

    public function test_a_code_is_accepted_once(): void
    {
        $user = UserModel::factory()->create();
        $secret = $this->enable($user, confirmAtOffset: -1);

        $this->assertTrue($this->service()->verify((string) $user->id, $this->codeAt($secret, 0)));
        $this->assertFalse($this->service()->verify((string) $user->id, $this->codeAt($secret, 0)));
    }

    public function test_once_a_step_is_used_no_earlier_code_is_accepted(): void
    {
        $user = UserModel::factory()->create();
        $secret = $this->enable($user, confirmAtOffset: -1);

        $this->assertTrue($this->service()->verify((string) $user->id, $this->codeAt($secret, 1)));
        $this->assertFalse($this->service()->verify((string) $user->id, $this->codeAt($secret, 0)));
    }

    public function test_the_code_used_to_confirm_cannot_sign_in(): void
    {
        $user = UserModel::factory()->create();
        $secret = $this->enable($user, confirmAtOffset: 0);

        $this->assertFalse($this->service()->verify((string) $user->id, $this->codeAt($secret, 0)));
        $this->assertTrue($this->service()->verify((string) $user->id, $this->codeAt($secret, 1)));
    }

    public function test_one_step_of_leeway_on_each_side_and_no_more(): void
    {
        $user = UserModel::factory()->create();
        $secret = $this->enable($user, confirmAtOffset: -5);

        $this->assertFalse($this->service()->verify((string) $user->id, $this->codeAt($secret, -2)));
        $this->assertFalse($this->service()->verify((string) $user->id, $this->codeAt($secret, 2)));
        $this->assertTrue($this->service()->verify((string) $user->id, $this->codeAt($secret, -1)));
        $this->assertTrue($this->service()->verify((string) $user->id, $this->codeAt($secret, 1)));
    }

    public function test_spaces_in_a_code_are_ignored_and_malformed_ones_refused(): void
    {
        $user = UserModel::factory()->create();
        $secret = $this->enable($user, confirmAtOffset: -1);
        $code = $this->codeAt($secret, 0);

        $this->assertFalse($this->service()->verify((string) $user->id, 'abcdef'));
        $this->assertFalse($this->service()->verify((string) $user->id, ''));
        $this->assertTrue($this->service()->verify((string) $user->id, substr($code, 0, 3).' '.substr($code, 3)));
    }

    public function test_no_code_passes_while_it_is_off_or_pending(): void
    {
        $user = UserModel::factory()->create();
        $this->assertFalse($this->service()->verify((string) $user->id, '123456'));

        $setup = $this->service()->beginSetup((string) $user->id);
        $this->assertFalse($this->service()->verify((string) $user->id, $this->codeAt($setup->secret, 0)));
        $this->assertFalse($this->service()->useRecoveryCode((string) $user->id, 'abcde-fghjk'));
    }

    public function test_a_recovery_code_works_once(): void
    {
        Event::fake([RecoveryCodeUsed::class]);
        $user = UserModel::factory()->create();
        $setup = $this->service()->beginSetup((string) $user->id);
        $codes = $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0));

        $this->assertTrue($this->service()->useRecoveryCode((string) $user->id, strtoupper(str_replace('-', ' ', $codes[3]))));
        $this->assertFalse($this->service()->useRecoveryCode((string) $user->id, $codes[3]));
        $this->assertFalse($this->service()->useRecoveryCode((string) $user->id, 'zzzzz-zzzzz'));
        $this->assertFalse($this->service()->useRecoveryCode((string) $user->id, 'not a code'));

        $this->assertSame(7, $this->service()->status((string) $user->id)->recoveryCodesRemaining);
        Event::assertDispatchedTimes(RecoveryCodeUsed::class, 1);
        Event::assertDispatched(RecoveryCodeUsed::class, static fn (RecoveryCodeUsed $event): bool => $event->userId === (string) $user->id && $event->remaining === 7);
        // Recovery codes are no TOTP codes, and the TOTP still works
        $this->assertTrue($this->service()->verify((string) $user->id, $this->codeAt($setup->secret, 1)));
    }

    public function test_codes_are_checked_and_used_under_a_row_lock(): void
    {
        $user = UserModel::factory()->create();
        $setup = $this->service()->beginSetup((string) $user->id);
        $codes = $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, -1));

        $reads = [];
        $base = DB::transactionLevel();
        DB::listen(static function (QueryExecuted $query) use (&$reads): void {
            if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'user_two_factor')) {
                $reads[] = ['sql' => strtolower($query->sql), 'level' => DB::transactionLevel()];
            }
        });

        $this->service()->verify((string) $user->id, $this->codeAt($setup->secret, 0));
        $this->service()->useRecoveryCode((string) $user->id, $codes[0]);

        $this->assertCount(2, $reads);
        foreach ($reads as $read) {
            $this->assertGreaterThan($base, $read['level']);
            if (DB::getDriverName() !== 'sqlite') {
                $this->assertStringContainsString('for update', $read['sql']);
            }
        }
    }

    public function test_a_recovery_code_used_twice_in_one_transaction_passes_once(): void
    {
        $user = UserModel::factory()->create();
        $setup = $this->service()->beginSetup((string) $user->id);
        $codes = $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0));

        // Each attempt reads the row again under the lock, never a copy read before the other wrote
        $results = [];
        DB::transaction(function () use ($user, $codes, &$results): void {
            $results[] = $this->service()->useRecoveryCode((string) $user->id, $codes[0]);
            $results[] = $this->service()->useRecoveryCode((string) $user->id, $codes[0]);
        });

        $this->assertSame([true, false], $results);
        $this->assertSame(7, $this->service()->status((string) $user->id)->recoveryCodesRemaining);
    }

    public function test_regenerating_replaces_every_recovery_code(): void
    {
        Event::fake([RecoveryCodesRegenerated::class]);
        $user = UserModel::factory()->create();
        $setup = $this->service()->beginSetup((string) $user->id);
        $old = $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0));
        $this->service()->useRecoveryCode((string) $user->id, $old[0]);

        $new = $this->service()->regenerateRecoveryCodes((string) $user->id);

        $this->assertCount(8, $new);
        $this->assertSame([], array_intersect($old, $new));
        $this->assertSame(8, $this->service()->status((string) $user->id)->recoveryCodesRemaining);
        $this->assertFalse($this->service()->useRecoveryCode((string) $user->id, $old[1]));
        $this->assertTrue($this->service()->useRecoveryCode((string) $user->id, $new[1]));
        Event::assertDispatched(RecoveryCodesRegenerated::class);

        $other = UserModel::factory()->create();
        $this->assertRefused(TwoFactorFailure::NotEnabled, fn () => $this->service()->regenerateRecoveryCodes((string) $other->id));
    }

    public function test_turning_it_off_deletes_the_secret_and_the_codes(): void
    {
        Event::fake([TwoFactorDisabled::class]);
        $user = UserModel::factory()->create();
        $this->enable($user);

        $this->service()->disable((string) $user->id);

        $this->assertNull(UserTwoFactorModel::query()->find($user->id));
        $this->assertFalse($this->service()->isEnabled((string) $user->id));
        $this->assertFalse($this->service()->status((string) $user->id)->enabled);
        Event::assertDispatched(TwoFactorDisabled::class, static fn (TwoFactorDisabled $event): bool => $event->userId === (string) $user->id);

        $this->assertRefused(TwoFactorFailure::NotEnabled, fn () => $this->service()->disable((string) $user->id));
    }

    public function test_a_pending_setup_cannot_be_turned_off_as_if_it_were_on(): void
    {
        Event::fake([TwoFactorDisabled::class]);
        $user = UserModel::factory()->create();
        $this->service()->beginSetup((string) $user->id);

        $this->assertRefused(TwoFactorFailure::NotEnabled, fn () => $this->service()->disable((string) $user->id));
        Event::assertNotDispatched(TwoFactorDisabled::class);
    }

    public function test_deleting_the_account_deletes_its_two_factor(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);

        $user->forceDelete();

        $this->assertSame(0, UserTwoFactorModel::query()->count());
    }

    public function test_it_tells_which_accounts_have_it_on(): void
    {
        $on = UserModel::factory()->create();
        $pending = UserModel::factory()->create();
        $off = UserModel::factory()->create();
        $this->enable($on);
        $this->service()->beginSetup((string) $pending->id);

        $reader = $this->app->make(TwoFactorStatusReaderInterface::class);

        $this->assertSame([(string) $on->id], $reader->enabledAmong([(string) $on->id, strtoupper((string) $pending->id), (string) $off->id, 'nope']));
        $this->assertSame([(string) $on->id], $reader->enabledAmong([strtoupper((string) $on->id)]));
        $this->assertSame([], $reader->enabledAmong([]));
        $this->assertTrue($reader->isEnabled(strtoupper((string) $on->id)));
    }

    public function test_turning_it_on_closes_the_other_sessions_and_remember_cookies_after_the_commit(): void
    {
        config(['session.driver' => 'database']);
        $user = UserModel::factory()->create(['remember_token' => 'old-remember-token']);
        $other = UserModel::factory()->create();
        $current = $this->sessionStore();
        $this->storeSession($current->getId(), $user);
        $this->storeSession('laptop', $user);
        $this->storeSession('phone', $user);
        $this->storeSession('someone-else', $other);
        $setup = $this->service()->beginSetup((string) $user->id);

        DB::transaction(function () use ($user, $setup, $current): void {
            $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0), $current);

            // Nothing is closed or stamped before the commit
            $this->assertSame(3, DB::table('sessions')->where('user_id', $user->id)->count());
            $this->assertFalse($current->has(TwoFactorSessionSeal::SESSION_KEY));
        });

        $this->assertEqualsCanonicalizing([$current->getId(), 'someone-else'], DB::table('sessions')->pluck('id')->all());
        $token = (string) $user->fresh()?->getRememberToken();
        $this->assertNotSame('old-remember-token', $token);
        $this->assertSame(60, strlen($token));

        // The session that turned it on stays signed in, with any driver; a session opened before does not
        $seal = $this->app->make(TwoFactorSessionSealInterface::class);
        $fresh = $user->refresh();
        $this->assertTrue($seal->passes($current, $fresh));
        $this->assertFalse($seal->passes($this->sessionStore(), $fresh));
    }

    public function test_without_a_current_session_every_session_closes(): void
    {
        config(['session.driver' => 'database']);
        $user = UserModel::factory()->create();
        $this->storeSession('laptop', $user);
        $setup = $this->service()->beginSetup((string) $user->id);

        $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0));

        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_a_rolled_back_confirmation_closes_nothing_and_announces_nothing(): void
    {
        Event::fake([TwoFactorEnabled::class]);
        config(['session.driver' => 'database']);
        $user = UserModel::factory()->create(['remember_token' => 'old-remember-token']);
        $this->storeSession('laptop', $user);
        $current = $this->sessionStore();
        $setup = $this->service()->beginSetup((string) $user->id);

        try {
            DB::transaction(function () use ($user, $setup, $current): void {
                $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0), $current);

                throw new \RuntimeException('Rolled back');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, DB::table('sessions')->count());
        $this->assertFalse($current->has(TwoFactorSessionSeal::SESSION_KEY));
        $this->assertSame('old-remember-token', $user->fresh()?->getRememberToken());
        $this->assertFalse($this->service()->isEnabled((string) $user->id));
        Event::assertNotDispatched(TwoFactorEnabled::class);
    }

    public function test_nothing_changes_while_impersonating(): void
    {
        $user = UserModel::factory()->create();
        $pending = UserModel::factory()->create();
        $this->enable($user);
        $setup = $this->service()->beginSetup((string) $pending->id);
        $before = $this->rows();
        Event::fake([TwoFactorEnabled::class, TwoFactorDisabled::class, RecoveryCodesRegenerated::class, RecoveryCodeUsed::class]);

        $impersonation = $this->createStub(ImpersonationContextInterface::class);
        $impersonation->method('isActive')->willReturn(true);
        $this->app->instance(ImpersonationContextInterface::class, $impersonation);

        $this->assertRefused(TwoFactorFailure::Impersonating, fn () => $this->service()->beginSetup((string) $pending->id));
        $this->assertRefused(TwoFactorFailure::Impersonating, fn () => $this->service()->confirmSetup((string) $pending->id, $this->codeAt($setup->secret, 0)));
        $this->assertRefused(TwoFactorFailure::Impersonating, fn () => $this->service()->regenerateRecoveryCodes((string) $user->id));
        $this->assertRefused(TwoFactorFailure::Impersonating, fn () => $this->service()->disable((string) $user->id));

        $this->assertSame($before, $this->rows());
        Event::assertNothingDispatched();
        $this->assertTrue($this->service()->isEnabled((string) $user->id));
    }

    public function test_a_failing_seal_write_rolls_the_confirmation_back(): void
    {
        $user = UserModel::factory()->create();
        $setup = $this->service()->beginSetup((string) $user->id);
        $seal = $this->createStub(TwoFactorSessionSealInterface::class);
        $seal->method('renew')->willThrowException(new \RuntimeException('Update failed'));
        $this->app->instance(TwoFactorSessionSealInterface::class, $seal);

        try {
            $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0));
            $this->fail('Expected the confirmation to fail');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Update failed', $exception->getMessage());
        }

        $this->assertFalse($this->service()->isEnabled((string) $user->id));
        $this->assertTrue($this->service()->status((string) $user->id)->setupPending);
    }

    public function test_a_failing_mail_never_costs_the_recovery_codes(): void
    {
        $log = Log::spy();
        $user = UserModel::factory()->create();
        $setup = $this->service()->beginSetup((string) $user->id);
        Event::listen(TwoFactorEnabled::class, static fn () => throw new \RuntimeException('Mail down'));

        $codes = $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0));

        $this->assertCount(8, $codes);
        $this->assertTrue($this->service()->isEnabled((string) $user->id));
        $log->shouldHaveReceived('error')->withArgs(
            static fn (string $message, array $context): bool => $context['event'] === TwoFactorEnabled::class
                && $context['exception'] === \RuntimeException::class,
        );
    }

    public function test_a_failing_code_computation_never_carries_the_secret(): void
    {
        $user = UserModel::factory()->create();
        $setup = $this->service()->beginSetup((string) $user->id);
        // An unreadable secret makes google2fa throw with it in the arguments of its trace
        UserTwoFactorModel::query()->whereKey($user->id)->update(['secret' => Crypt::encryptString('not base32 at all!')]);

        try {
            $this->service()->confirmSetup((string) $user->id, '123456');
            $this->fail('Expected the computation to fail');
        } catch (TwoFactorUnavailableException $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('not base32', $exception->getMessage());
            $this->assertStringNotContainsString($setup->secret, $exception->getTraceAsString());
        }
    }

    /**
     * @return list<array{string, string, int|null, int|null}>
     */
    private function rows(): array
    {
        return array_values(UserTwoFactorModel::query()->orderBy('user_id')->get()
            ->map(static fn (UserTwoFactorModel $row): array => [$row->user_id, $row->secret, $row->confirmed_at?->getTimestamp(), $row->last_used_timestep])
            ->all());
    }

    private function service(): TwoFactorServiceInterface
    {
        return $this->app->make(TwoFactorServiceInterface::class);
    }

    /**
     * Turns it on, at the step the given number of steps from now, and returns the secret.
     */
    private function enable(UserModel $user, int $confirmAtOffset = 0): string
    {
        // Confirmed back then, so the window of that moment accepts the code
        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW + 30 * $confirmAtOffset));
        $setup = $this->service()->beginSetup((string) $user->id);
        $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, $confirmAtOffset));
        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));

        return $setup->secret;
    }

    private function codeAt(string $secret, int $stepOffset): string
    {
        return (new Google2FA)->oathTotp($secret, intdiv(self::NOW, 30) + $stepOffset);
    }

    private function sessionStore(): Store
    {
        return new Store('test', new ArraySessionHandler(10), Str::random(40));
    }

    private function storeSession(string $id, UserModel $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => self::NOW,
        ]);
    }

    private function assertRefused(TwoFactorFailure $reason, Closure $call): void
    {
        try {
            $call();
            $this->fail('Expected the change to be refused: '.$reason->value);
        } catch (TwoFactorException $exception) {
            $this->assertSame($reason, $exception->reason);
        }
    }
}
