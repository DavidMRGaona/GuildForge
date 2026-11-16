<?php

declare(strict_types=1);

namespace Tests\Feature\TwoFactor;

use App\Application\TwoFactor\DTOs\TwoFactorSetupDTO;
use App\Application\TwoFactor\DTOs\TwoFactorStatusDTO;
use App\Application\TwoFactor\Services\TwoFactorChallengeInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Domain\TwoFactor\TwoFactorChallengeRule;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Filament\Auth\PanelLogin;
use App\Filament\Auth\TwoFactorAuthenticationProvider;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\TwoFactor\RepeatedTwoFactorCodeFailuresNotification;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The account's limits on codes (core#770) are one for the site and the panel together: five a
 * minute and thirty an hour, whatever the pending sign-in or the address; the tenth wrong code
 * within an hour mails the owner once.
 */
final class TwoFactorChallengeAccountLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const int NOW = 1_800_000_015;

    private const string PASSWORD = 'correct horse battery staple';

    private const string CHALLENGE = '/iniciar-sesion/verificacion';

    private string $secret = '';

    /** Codes the service was really asked to check */
    private int $verified = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_site_and_the_panel_share_the_limit_per_minute(): void
    {
        $user = $this->adminWithTwoFactor();
        $this->countVerifications();

        // Three wrong codes on the site
        $this->post('/iniciar-sesion', ['email' => $user->email, 'password' => self::PASSWORD]);
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(self::CHALLENGE, ['code' => '000000'])->assertSessionHasErrors('code');
        }

        // Two more in the panel reach the account's five
        $panel = Livewire::test(PanelLogin::class)
            ->fillForm(['email' => $user->email, 'password' => self::PASSWORD])
            ->call('authenticate');
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $panel->fillForm($this->panelCode('000000'), 'multiFactorChallengeForm')->call('authenticate');
        }
        $this->assertSame(TwoFactorChallengeRule::ATTEMPTS_PER_MINUTE, $this->verified);

        // The next one is refused in the panel without asking the service, even the right code
        $panel->fillForm($this->panelCode($this->codeAt(1)), 'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertHasErrors(['data.multiFactor.'.TwoFactorAuthenticationProvider::ID.'.code']);

        // ...and on the site
        $this->post(self::CHALLENGE, ['code' => $this->codeAt(1)])
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
            ->assertHeader('Retry-After');

        $this->assertSame(TwoFactorChallengeRule::ATTEMPTS_PER_MINUTE, $this->verified);
        $this->assertGuest();

        // A minute later the account may try again
        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        $this->post(self::CHALLENGE, ['code' => $this->codeAt(1)])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_thirty_codes_an_hour_at_most(): void
    {
        $user = $this->adminWithTwoFactor();
        $challenge = $this->app->make(TwoFactorChallengeInterface::class);

        for ($attempt = 1; $attempt <= TwoFactorChallengeRule::ATTEMPTS_PER_HOUR; $attempt++) {
            $this->assertSame(TwoFactorFailure::InvalidCode, $challenge->check((string) $user->id, '000000', false)->failure);

            if ($attempt % TwoFactorChallengeRule::ATTEMPTS_PER_MINUTE === 0) {
                Carbon::setTestNow(Carbon::now()->addSeconds(61));
            }
        }

        $refused = $challenge->check((string) $user->id, $this->codeAt(0), false);
        $this->assertSame(TwoFactorFailure::TooManyAttempts, $refused->failure);
        $this->assertGreaterThan(60, $refused->retryAfter);

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->assertTrue($challenge->check((string) $user->id, $this->codeAt(0), false)->passed);
    }

    public function test_the_tenth_wrong_code_within_an_hour_mails_the_owner_once(): void
    {
        Notification::fake();
        $user = $this->adminWithTwoFactor();
        $challenge = $this->app->make(TwoFactorChallengeInterface::class);

        for ($attempt = 1; $attempt <= 2 * TwoFactorChallengeRule::ALERT_AFTER_FAILURES; $attempt++) {
            $challenge->check((string) $user->id, '000000', $attempt % 2 === 0);

            if ($attempt === TwoFactorChallengeRule::ALERT_AFTER_FAILURES - 1) {
                Notification::assertNotSentTo($user, RepeatedTwoFactorCodeFailuresNotification::class);
            }

            if ($attempt % TwoFactorChallengeRule::ATTEMPTS_PER_MINUTE === 0) {
                Carbon::setTestNow(Carbon::now()->addSeconds(61));
            }
        }

        Notification::assertSentToTimes($user, RepeatedTwoFactorCodeFailuresNotification::class, 1);
    }

    public function test_the_mail_asks_to_change_the_password(): void
    {
        $mail = (new RepeatedTwoFactorCodeFailuresNotification('Sitio de prueba', Carbon::now()->toIso8601String()))
            ->toMail(UserModel::factory()->make());

        $this->assertSame(__('two_factor.mail.repeated_code_failures.subject', ['site' => 'Sitio de prueba']), $mail->subject);
        $this->assertContains(__('two_factor.mail.repeated_code_failures.not_you'), $mail->introLines);
    }

    private function adminWithTwoFactor(): UserModel
    {
        $user = UserModel::factory()->admin()->create(['password' => self::PASSWORD]);
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $this->secret = $service->beginSetup((string) $user->id)->secret;
        $service->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($this->secret, intdiv(self::NOW, 30)));

        return $user;
    }

    private function codeAt(int $offset): string
    {
        return (new Google2FA)->oathTotp($this->secret, intdiv(Carbon::now()->getTimestamp(), 30) + $offset);
    }

    /**
     * @return array<string, array{code: string, recovery: bool}>
     */
    private function panelCode(string $code): array
    {
        return [TwoFactorAuthenticationProvider::ID => ['code' => $code, 'recovery' => false]];
    }

    /**
     * Counts what reaches the service, so a refusal by the limit can be told from a wrong code.
     */
    private function countVerifications(): void
    {
        $real = $this->app->make(TwoFactorServiceInterface::class);
        $count = function (): void {
            $this->verified++;
        };

        $this->app->instance(TwoFactorServiceInterface::class, new readonly class($real, $count) implements TwoFactorServiceInterface
        {
            public function __construct(private TwoFactorServiceInterface $real, private Closure $count) {}

            public function verify(string $userId, string $code): bool
            {
                ($this->count)();

                return $this->real->verify($userId, $code);
            }

            public function useRecoveryCode(string $userId, string $code): bool
            {
                ($this->count)();

                return $this->real->useRecoveryCode($userId, $code);
            }

            public function status(string $userId): TwoFactorStatusDTO
            {
                return $this->real->status($userId);
            }

            public function beginSetup(string $userId): TwoFactorSetupDTO
            {
                return $this->real->beginSetup($userId);
            }

            public function confirmSetup(string $userId, string $code, ?Session $currentSession = null): array
            {
                return $this->real->confirmSetup($userId, $code, $currentSession);
            }

            public function regenerateRecoveryCodes(string $userId): array
            {
                return $this->real->regenerateRecoveryCodes($userId);
            }

            public function disable(string $userId, ?Session $currentSession = null): void
            {
                $this->real->disable($userId, $currentSession);
            }

            public function isEnabled(string $userId): bool
            {
                return $this->real->isEnabled($userId);
            }

            public function enabledAmong(array $userIds): array
            {
                return $this->real->enabledAmong($userIds);
            }
        });
    }
}
