<?php

declare(strict_types=1);

namespace Tests\Feature\TwoFactor;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorResetInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\TwoFactor\TwoFactorResetNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * The person is told when the administration takes their two-factor authentication away (core#771):
 * always, whatever their mail preferences, and never naming who did it.
 */
final class TwoFactorResetMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const int NOW = 1_800_000_015;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        config(['app.display_timezone' => 'Europe/Madrid']);
        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));
        app(SettingsServiceInterface::class)->set('guild_name', 'Example *site*');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_resetting_it_mails_the_owner(): void
    {
        $user = UserModel::factory()->create();
        $actor = UserModel::factory()->admin()->create();
        $this->enable($user);
        Notification::fake();

        $this->app->make(TwoFactorResetInterface::class)->reset((string) $user->id, (string) $actor->id);

        Notification::assertSentTo($user, TwoFactorResetNotification::class, function (TwoFactorResetNotification $notification) use ($user, $actor): bool {
            $mail = $notification->toMail($user);
            $text = $this->text($mail);

            $this->assertSame(['mail'], $notification->via($user));
            $this->assertSame(CoreMailCategories::ACCOUNT_SECURITY, $notification->mailCategory());
            $this->assertSame('La administración ha quitado el doble factor de tu cuenta de Example *site*', $mail->subject);
            $this->assertStringContainsString('La administración de Example \*site\* ha quitado', $text);
            $this->assertStringContainsString(app(TimezoneServiceInterface::class)->format(Carbon::createFromTimestamp(self::NOW)), $text);
            // Its own closing line: it was the administration, not the person
            $this->assertStringContainsString('Si no lo has pedido tú, cambia tu contraseña', $text);
            $this->assertStringNotContainsString('Si no has sido tú', $text);
            $this->assertStringNotContainsString((string) $actor->name, $text);
            $this->assertSame(route('profile.show'), $mail->actionUrl);

            return true;
        });
        Notification::assertCount(1);
    }

    public function test_a_deactivated_account_is_told_too(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $user->delete();
        Notification::fake();

        $this->app->make(TwoFactorResetInterface::class)->reset((string) $user->id, null);

        Notification::assertSentTo($user, TwoFactorResetNotification::class);
    }

    public function test_an_anonymized_account_is_never_mailed(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $user->forceFill(['anonymized_at' => now(), 'deleted_at' => now()])->save();
        Notification::fake();

        $this->app->make(TwoFactorResetInterface::class)->reset((string) $user->id, null);

        Notification::assertNothingSent();
    }

    public function test_an_account_without_two_factor_gets_no_mail(): void
    {
        $user = UserModel::factory()->create();
        Notification::fake();

        $this->app->make(TwoFactorResetInterface::class)->reset((string) $user->id, null);

        Notification::assertNothingSent();
    }

    private function enable(UserModel $user): void
    {
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $setup = $service->beginSetup((string) $user->id);
        $service->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30)));
    }

    private function text(MailMessage $mail): string
    {
        return implode("\n", array_map(static fn (mixed $line): string => (string) $line, [...$mail->introLines, ...$mail->outroLines]));
    }
}
