<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\TwoFactor;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorAppChangeInterface;
use App\Application\TwoFactor\Services\TwoFactorResetInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Domain\TwoFactor\Events\TwoFactorAppChanged;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use App\Notifications\TwoFactor\TwoFactorAppChangedNotification;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Changing the authenticator app of an account that has two-factor authentication on (core#769):
 * the app in use keeps working until a code from the new one is confirmed, the recovery codes stay
 * and, as when turning it on or off, every other session is signed out (the one that changed it
 * is kept).
 */
final class TwoFactorAppChangeTest extends TestCase
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

    public function test_starting_returns_a_new_key_and_qr_and_keeps_the_current_app_working(): void
    {
        $user = UserModel::factory()->create();
        [$old, $codes] = $this->enableWithCodes($user);

        $setup = $this->change()->beginAppChange((string) $user->id);

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $setup->secret);
        $this->assertNotSame($old, $setup->secret);
        $this->assertStringStartsWith('<svg', $setup->qrCodeSvg);
        $this->assertStringStartsWith('otpauth://totp/', $setup->otpauthUri);
        // The app in use still signs in; the new one does not, until it is confirmed
        $this->assertFalse($this->service()->verify((string) $user->id, $this->codeAt($setup->secret, 1)));
        $this->assertTrue($this->service()->verify((string) $user->id, $this->codeAt($old, 1)));
        $this->assertTrue($this->service()->status((string) $user->id)->enabled);
        $this->assertCount(8, $codes);
    }

    public function test_the_new_secret_waits_encrypted_apart_from_the_current_one(): void
    {
        $user = UserModel::factory()->create();
        $old = $this->enable($user);

        $setup = $this->change()->beginAppChange((string) $user->id);

        $row = DB::table('user_two_factor')->where('user_id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString($setup->secret, json_encode($row, JSON_THROW_ON_ERROR));
        $this->assertSame($setup->secret, Crypt::decryptString($row->pending_secret));
        $this->assertSame($old, UserTwoFactorModel::query()->findOrFail($user->id)->secret);
        $this->assertArrayNotHasKey('pending_secret', UserTwoFactorModel::query()->findOrFail($user->id)->toArray());
    }

    public function test_confirming_with_a_code_of_the_new_app_swaps_the_secret_and_keeps_the_rest(): void
    {
        Event::fake([TwoFactorAppChanged::class]);
        $user = UserModel::factory()->create();
        $old = $this->enable($user);
        $before = (string) UserModel::query()->findOrFail($user->id)->session_seal;
        $setup = $this->change()->beginAppChange((string) $user->id);
        $session = $this->sessionStore();

        $this->change()->confirmAppChange((string) $user->id, $this->codeAt($setup->secret, 1), $session);

        $row = UserTwoFactorModel::query()->findOrFail($user->id);
        $this->assertSame($setup->secret, $row->secret);
        $this->assertNull(DB::table('user_two_factor')->where('user_id', $user->id)->value('pending_secret'));
        $this->assertSame(8, $this->service()->status((string) $user->id)->recoveryCodesRemaining);
        // Every other session is signed out (the seal changed), the one that changed it is kept
        $after = (string) UserModel::query()->findOrFail($user->id)->session_seal;
        $this->assertNotSame($before, $after);
        $this->assertSame($after, $session->get(TwoFactorSessionSeal::SESSION_KEY));
        // The old app no longer signs in; the new one does, once the step has moved on
        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW + 30));
        $this->assertFalse($this->service()->verify((string) $user->id, $this->codeAt($old, 2)));
        $this->assertTrue($this->service()->verify((string) $user->id, $this->codeAt($setup->secret, 2)));
        Event::assertDispatched(TwoFactorAppChanged::class, static fn (TwoFactorAppChanged $event): bool => $event->userId === (string) $user->id);
    }

    public function test_the_code_that_confirmed_the_change_cannot_be_used_again(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $setup = $this->change()->beginAppChange((string) $user->id);
        $code = $this->codeAt($setup->secret, 1);

        $this->change()->confirmAppChange((string) $user->id, $code);

        $this->assertFalse($this->service()->verify((string) $user->id, $code));
    }

    public function test_a_wrong_code_or_one_of_the_current_app_changes_nothing(): void
    {
        $user = UserModel::factory()->create();
        $old = $this->enable($user);
        $setup = $this->change()->beginAppChange((string) $user->id);

        $this->assertRefused(TwoFactorFailure::InvalidCode, fn () => $this->change()->confirmAppChange((string) $user->id, '000000'));
        $this->assertRefused(TwoFactorFailure::InvalidCode, fn () => $this->change()->confirmAppChange((string) $user->id, $this->codeAt($old, 1)));
        $this->assertRefused(TwoFactorFailure::InvalidCode, fn () => $this->change()->confirmAppChange((string) $user->id, 'not a code'));

        $this->assertSame($old, UserTwoFactorModel::query()->findOrFail($user->id)->secret);
        // The new one can still be confirmed afterwards
        $this->change()->confirmAppChange((string) $user->id, $this->codeAt($setup->secret, 1));
        $this->assertSame($setup->secret, UserTwoFactorModel::query()->findOrFail($user->id)->secret);
    }

    public function test_starting_again_replaces_the_secret_waiting(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $first = $this->change()->beginAppChange((string) $user->id);
        $second = $this->change()->beginAppChange((string) $user->id);

        $this->assertNotSame($first->secret, $second->secret);
        $this->assertRefused(TwoFactorFailure::InvalidCode, fn () => $this->change()->confirmAppChange((string) $user->id, $this->codeAt($first->secret, 1)));
        $this->change()->confirmAppChange((string) $user->id, $this->codeAt($second->secret, 1));
    }

    public function test_confirming_without_starting_is_refused(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);

        $this->assertRefused(TwoFactorFailure::NoPendingSetup, fn () => $this->change()->confirmAppChange((string) $user->id, '123456'));
    }

    public function test_an_account_without_it_has_nothing_to_change(): void
    {
        $user = UserModel::factory()->create();
        $pending = UserModel::factory()->create();
        $this->service()->beginSetup((string) $pending->id);

        $this->assertRefused(TwoFactorFailure::NotEnabled, fn () => $this->change()->beginAppChange((string) $user->id));
        $this->assertRefused(TwoFactorFailure::NotEnabled, fn () => $this->change()->beginAppChange((string) $pending->id));
        $this->assertRefused(TwoFactorFailure::NotEnabled, fn () => $this->change()->confirmAppChange((string) $pending->id, '123456'));
        $this->assertRefused(TwoFactorFailure::NotEnabled, fn () => $this->change()->beginAppChange('not-a-uuid'));
    }

    public function test_turning_it_off_and_on_again_leaves_no_secret_waiting(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $this->change()->beginAppChange((string) $user->id);

        $this->service()->disable((string) $user->id);
        $this->service()->beginSetup((string) $user->id);

        $this->assertNull(DB::table('user_two_factor')->where('user_id', $user->id)->value('pending_secret'));
    }

    public function test_turning_it_off_deletes_the_secret_waiting(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $this->change()->beginAppChange((string) $user->id);

        $this->service()->disable((string) $user->id);

        $this->assertSame(0, DB::table('user_two_factor')->where('user_id', $user->id)->count());
    }

    public function test_an_administration_reset_leaves_no_secret_waiting(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $this->change()->beginAppChange((string) $user->id);

        $this->assertTrue($this->app->make(TwoFactorResetInterface::class)->reset((string) $user->id, null));

        // The whole row goes, the pending secret with it
        $this->assertSame(0, DB::table('user_two_factor')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('user_two_factor')->whereNotNull('pending_secret')->count());
        $this->assertFalse($this->service()->status((string) $user->id)->enabled);
    }

    public function test_nothing_changes_while_impersonating(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $setup = $this->change()->beginAppChange((string) $user->id);
        $impersonation = $this->createStub(ImpersonationContextInterface::class);
        $impersonation->method('isActive')->willReturn(true);
        $this->app->instance(ImpersonationContextInterface::class, $impersonation);

        $this->assertRefused(TwoFactorFailure::Impersonating, fn () => $this->change()->beginAppChange((string) $user->id));
        $this->assertRefused(TwoFactorFailure::Impersonating, fn () => $this->change()->confirmAppChange((string) $user->id, $this->codeAt($setup->secret, 1)));
    }

    public function test_the_change_mails_the_owner(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        Notification::fake();
        $setup = $this->change()->beginAppChange((string) $user->id);
        Notification::assertNothingSent();

        $this->change()->confirmAppChange((string) $user->id, $this->codeAt($setup->secret, 1));

        Notification::assertSentTo($user, TwoFactorAppChangedNotification::class, function (TwoFactorAppChangedNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            $this->assertSame('Aplicación de doble factor cambiada en Example [site]', $mail->subject);
            $this->assertStringContainsString('códigos de recuperación siguen valiendo', implode(' ', $mail->introLines));

            return true;
        });
    }

    private function sessionStore(): Store
    {
        return new Store('test', new ArraySessionHandler(10), Str::random(40));
    }

    public function test_a_code_of_the_app_used_a_moment_ago_does_not_make_the_new_app_wait(): void
    {
        $user = UserModel::factory()->create();
        $old = $this->enable($user);
        $setup = $this->change()->beginAppChange((string) $user->id);
        // The code of the app in use, typed to prove who is changing it, takes the step after the first
        $this->service()->verify((string) $user->id, $this->codeAt($old, 1));

        // The new app shows the same step's code: it is its first use, so it is accepted
        $this->change()->confirmAppChange((string) $user->id, $this->codeAt($setup->secret, 1));

        $this->assertSame($setup->secret, UserTwoFactorModel::query()->findOrFail($user->id)->secret);
        // ...and it cannot be used again, nor an older one, to sign in
        $this->assertFalse($this->service()->verify((string) $user->id, $this->codeAt($setup->secret, 1)));
        $this->assertFalse($this->service()->verify((string) $user->id, $this->codeAt($setup->secret, 0)));
    }

    private function change(): TwoFactorAppChangeInterface
    {
        return $this->app->make(TwoFactorAppChangeInterface::class);
    }

    private function service(): TwoFactorServiceInterface
    {
        return $this->app->make(TwoFactorServiceInterface::class);
    }

    /**
     * Turns it on and returns the secret.
     */
    private function enable(UserModel $user): string
    {
        return $this->enableWithCodes($user)[0];
    }

    /**
     * Turns it on and returns the secret and the recovery codes.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function enableWithCodes(UserModel $user): array
    {
        $setup = $this->service()->beginSetup((string) $user->id);
        $codes = $this->service()->confirmSetup((string) $user->id, $this->codeAt($setup->secret, 0));

        return [$setup->secret, $codes];
    }

    private function codeAt(string $secret, int $stepOffset): string
    {
        return (new Google2FA)->oathTotp($secret, intdiv(self::NOW, 30) + $stepOffset);
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
