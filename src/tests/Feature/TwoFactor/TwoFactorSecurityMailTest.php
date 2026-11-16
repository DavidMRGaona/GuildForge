<?php

declare(strict_types=1);

namespace Tests\Feature\TwoFactor;

use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\TwoFactor\RecoveryCodesRegeneratedNotification;
use App\Notifications\TwoFactor\RecoveryCodeUsedNotification;
use App\Notifications\TwoFactor\TwoFactorDisabledNotification;
use App\Notifications\TwoFactor\TwoFactorEnabledNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Every change to someone's two-factor authentication mails them, always (core#768).
 */
final class TwoFactorSecurityMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * The frozen clock: the middle of the previous 30-second step, so always a moment just past.
     * Never a fixed date: the first test of a process runs the migrations under it, and some of them
     * stamp the time (the email verification cutoff), which later test classes compare with now.
     */
    private int $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = intdiv(time(), 30) * 30 - 15;

        app()->setLocale('es');
        config(['app.display_timezone' => 'Europe/Madrid']);
        Carbon::setTestNow(Carbon::createFromTimestamp($this->now));
        app(SettingsServiceInterface::class)->set('guild_name', 'Example *site*');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_category_is_mandatory_and_listed_with_the_account(): void
    {
        $category = $this->app->make(MailCategoryRegistryInterface::class)->find(CoreMailCategories::ACCOUNT_SECURITY);

        $this->assertNotNull($category);
        $this->assertTrue($category->isMandatory());
        $this->assertTrue($category->mail);
        $this->assertFalse($category->site);
        $this->assertSame('mail.preferences.sections.account', $category->section);
        $this->assertSame('Seguridad de la cuenta', __($category->label));
        $this->assertStringStartsWith('Avisos de seguridad de tu cuenta', __((string) $category->mandatoryReason));
    }

    public function test_turning_it_on_mails_the_owner(): void
    {
        $user = UserModel::factory()->create();

        $this->enable($user);

        Notification::assertSentTo($user, TwoFactorEnabledNotification::class, function (TwoFactorEnabledNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            $this->assertSame(['mail'], $notification->via($user));
            $this->assertSame(CoreMailCategories::ACCOUNT_SECURITY, $notification->mailCategory());
            $this->assertSame('Doble factor activado en Example *site*', $mail->subject);
            $this->assertStringContainsString('Example \*site\*', $this->text($mail));
            $this->assertStringContainsString(app(TimezoneServiceInterface::class)->format(Carbon::createFromTimestamp($this->now)), $this->text($mail));
            $this->assertStringContainsString('Si no has sido tú', $this->text($mail));
            $this->assertSame(route('profile.show'), $mail->actionUrl);

            return true;
        });
        Notification::assertCount(1);
    }

    public function test_using_a_recovery_code_mails_how_many_are_left(): void
    {
        $user = UserModel::factory()->create();
        $codes = $this->enable($user);

        $this->service()->useRecoveryCode((string) $user->id, $codes[0]);

        Notification::assertSentTo($user, RecoveryCodeUsedNotification::class, function (RecoveryCodeUsedNotification $notification) use ($user): bool {
            $text = $this->text($notification->toMail($user));

            $this->assertStringContainsString('Te quedan 7 códigos de recuperación.', $text);
            $this->assertStringNotContainsString('Te quedan pocos', $text);

            return true;
        });
    }

    public function test_few_recovery_codes_left_asks_for_new_ones(): void
    {
        $user = UserModel::factory()->create();

        $one = $this->text((new RecoveryCodeUsedNotification('Site', '2027-01-15T10:00:00+00:00', 1))->toMail($user));
        $none = $this->text((new RecoveryCodeUsedNotification('Site', '2027-01-15T10:00:00+00:00', 0))->toMail($user));

        $this->assertStringContainsString('Te queda 1 código de recuperación.', $one);
        $this->assertStringContainsString('Te quedan pocos', $one);
        $this->assertStringContainsString('No te queda ningún código de recuperación.', $none);
    }

    public function test_new_recovery_codes_and_turning_it_off_mail_the_owner(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);

        $this->service()->regenerateRecoveryCodes((string) $user->id);
        $this->service()->disable((string) $user->id);

        Notification::assertSentTo($user, RecoveryCodesRegeneratedNotification::class);
        Notification::assertSentTo($user, TwoFactorDisabledNotification::class, function (TwoFactorDisabledNotification $notification) use ($user): bool {
            $this->assertSame('Doble factor desactivado en Example *site*', $notification->toMail($user)->subject);

            return true;
        });
    }

    public function test_a_wrong_code_mails_nothing(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        Notification::fake();

        $this->service()->useRecoveryCode((string) $user->id, 'zzzzz-zzzzz');
        $this->service()->verify((string) $user->id, '000000');

        Notification::assertNothingSent();
    }

    /**
     * @return list<string>
     */
    private function enable(UserModel $user): array
    {
        $setup = $this->service()->beginSetup((string) $user->id);

        return $this->service()->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv($this->now, 30)));
    }

    private function service(): TwoFactorServiceInterface
    {
        return $this->app->make(TwoFactorServiceInterface::class);
    }

    private function text(MailMessage $mail): string
    {
        return implode("\n", array_map(static fn (mixed $line): string => (string) $line, [...$mail->introLines, ...$mail->outroLines]));
    }
}
