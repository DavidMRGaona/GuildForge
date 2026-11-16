<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use App\Notifications\TwoFactor\TwoFactorResetNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Console\Exception\RuntimeException;
use Tests\TestCase;

/**
 * `users:reset-two-factor` (core#771): the way back in when the only administrator loses their phone
 * and their recovery codes. It asks before it acts unless told not to, and does what the panel does.
 */
final class ResetTwoFactorCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const int NOW = 1_800_000_015;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));
        $this->user = UserModel::factory()->admin()->create(['email' => 'Ana@Example.org']);
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $setup = $service->beginSetup((string) $this->user->id);
        $service->confirmSetup((string) $this->user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30)));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_resets_after_confirmation_and_tells_the_owner(): void
    {
        Notification::fake();
        $log = Log::spy();

        $this->artisanCommand('users:reset-two-factor', ['email' => 'ana@example.org'])
            ->expectsConfirmation($this->question(), 'yes')
            ->expectsOutputToContain('Doble factor quitado')
            ->assertSuccessful();

        $this->assertNull(UserTwoFactorModel::query()->find($this->user->id));
        Notification::assertSentTo($this->user, TwoFactorResetNotification::class);
        $log->shouldHaveReceived('info')
            ->with('Two-factor reset from the command', ['source' => 'command', 'user_id' => (string) $this->user->id])
            ->once();
    }

    public function test_it_changes_nothing_when_the_answer_is_no(): void
    {
        Notification::fake();

        $this->artisanCommand('users:reset-two-factor', ['email' => 'ana@example.org'])
            ->expectsConfirmation($this->question(), 'no')
            ->expectsOutputToContain('Cancelado')
            ->assertFailed();

        $this->assertTrue(UserTwoFactorModel::query()->whereKey($this->user->id)->exists());
        Notification::assertNothingSent();
    }

    public function test_force_skips_the_question(): void
    {
        Notification::fake();

        $this->artisanCommand('users:reset-two-factor', ['email' => 'ana@example.org', '--force' => true])
            ->assertSuccessful();

        $this->assertNull(UserTwoFactorModel::query()->find($this->user->id));
        Notification::assertSentTo($this->user, TwoFactorResetNotification::class);
    }

    public function test_it_also_works_on_a_deactivated_account_and_still_tells_the_owner(): void
    {
        $this->user->delete();
        Notification::fake();

        $this->artisanCommand('users:reset-two-factor', ['email' => 'ana@example.org', '--force' => true])
            ->assertSuccessful();

        $this->assertNull(UserTwoFactorModel::query()->find($this->user->id));
        Notification::assertSentTo($this->user, TwoFactorResetNotification::class);
    }

    public function test_the_exact_address_wins_over_one_that_differs_in_capitals(): void
    {
        $upper = $this->accountWithCapitals();
        Notification::fake();

        $this->artisanCommand('users:reset-two-factor', ['email' => 'ana@example.org', '--force' => true])
            ->assertSuccessful();

        $this->assertNull(UserTwoFactorModel::query()->find($this->user->id));
        $this->assertTrue(UserTwoFactorModel::query()->whereKey($upper->id)->exists());
        Notification::assertSentTo($this->user, TwoFactorResetNotification::class);
        Notification::assertNotSentTo($upper, TwoFactorResetNotification::class);
    }

    public function test_addresses_that_differ_only_in_capitals_are_never_guessed(): void
    {
        $upper = $this->accountWithCapitals();
        Notification::fake();

        $this->artisanCommand('users:reset-two-factor', ['email' => 'ANA@EXAMPLE.ORG', '--force' => true])
            ->expectsOutputToContain('Hay 2 cuentas cuyo correo solo se distingue en las mayúsculas')
            ->assertFailed();

        $this->assertTrue(UserTwoFactorModel::query()->whereKey($this->user->id)->exists());
        $this->assertTrue(UserTwoFactorModel::query()->whereKey($upper->id)->exists());
        Notification::assertNothingSent();
    }

    public function test_an_unknown_address_fails_and_says_so(): void
    {
        $this->artisanCommand('users:reset-two-factor', ['email' => 'nadie@example.org', '--force' => true])
            ->expectsOutputToContain('No hay ninguna cuenta con ese correo')
            ->assertFailed();

        $this->assertTrue(UserTwoFactorModel::query()->whereKey($this->user->id)->exists());
    }

    public function test_an_account_without_two_factor_has_nothing_to_reset(): void
    {
        $other = UserModel::factory()->create(['email' => 'otra@example.org']);
        Notification::fake();
        $log = Log::spy();

        $this->artisanCommand('users:reset-two-factor', ['email' => 'otra@example.org', '--force' => true])
            ->expectsOutputToContain('no tiene el doble factor activado')
            ->assertSuccessful();

        Notification::assertNothingSent();
        $log->shouldNotHaveReceived('info', ['Two-factor reset from the command', Mockery::any()]);
        $this->assertNotNull($other->fresh());
    }

    public function test_the_address_is_required(): void
    {
        $this->expectException(RuntimeException::class);

        $this->artisanCommand('users:reset-two-factor');
    }

    /**
     * The model lowercases addresses, so one that differs only in capitals (it can come from elsewhere)
     * is written straight to the table.
     */
    private function accountWithCapitals(): UserModel
    {
        $account = UserModel::factory()->create();
        DB::table('users')->where('id', $account->id)->update(['email' => 'Ana@Example.org']);
        $this->enable($account);

        return $account;
    }

    private function question(): string
    {
        return sprintf('¿Quitar el doble factor de la cuenta ana@example.org (%s, id %s)?', $this->user->display_name ?: $this->user->name, $this->user->id);
    }

    private function enable(UserModel $user): void
    {
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $setup = $service->beginSetup((string) $user->id);
        $service->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30)));
    }
}
