<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Application\Legal\Services\LegalMailNoticeServiceInterface;
use App\Application\Mail\Services\MailConfigurationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Mail\TestMail;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The association name set in the admin settings is the site's name everywhere;
 * APP_NAME only stands in while it is empty.
 */
final class SiteNameTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string INSTALLATION_NAME = 'Instalación genérica';

    private const string SITE_NAME = 'Asociación de ejemplo';

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        config(['app.name' => self::INSTALLATION_NAME]);
        app(SettingsServiceInterface::class)->set('guild_name', self::SITE_NAME);
    }

    public function test_pages_share_the_site_name(): void
    {
        $this->get('/calendario')->assertInertia(
            fn (Assert $page) => $page
                ->where('appName', self::SITE_NAME)
                ->where('seo.siteName', self::SITE_NAME),
        );
    }

    public function test_the_server_rendered_head_uses_the_site_name(): void
    {
        $this->get('/calendario')
            ->assertOk()
            ->assertSee('<meta property="og:site_name" content="'.self::SITE_NAME.'" data-seo-server>', false)
            ->assertDontSee(self::INSTALLATION_NAME);
    }

    public function test_installation_name_stands_in_while_the_setting_is_blank(): void
    {
        app(SettingsServiceInterface::class)->set('guild_name', '   ');

        $this->get('/nosotros')->assertInertia(
            fn (Assert $page) => $page
                ->where('appName', self::INSTALLATION_NAME)
                ->where('guildName', self::INSTALLATION_NAME),
        );
    }

    public function test_notification_emails_use_the_site_name(): void
    {
        $html = (string) (new MailMessage)->line('Contenido.')->render();

        $this->assertStringContainsString(self::SITE_NAME, $html);
        $this->assertStringNotContainsString(self::INSTALLATION_NAME, $html);
    }

    public function test_the_test_email_uses_the_site_name(): void
    {
        $mail = new TestMail('smtp', '19/10/2026 18:00');

        $this->assertSame(self::SITE_NAME.': email de prueba', $mail->envelope()->subject);
        $this->assertStringNotContainsString(self::INSTALLATION_NAME, $mail->render());
    }

    public function test_the_welcome_email_uses_the_site_name(): void
    {
        Notification::fake();

        $this->post('/registro', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $user = UserModel::query()->where('email', 'john@example.com')->sole();
        Notification::assertSentTo(
            $user,
            WelcomeNotification::class,
            fn (WelcomeNotification $notification): bool => str_contains(
                (string) $notification->toMail($user)->subject,
                self::SITE_NAME,
            ),
        );
    }

    public function test_the_legal_entity_falls_back_to_the_site_name(): void
    {
        $this->assertSame(self::SITE_NAME, app(LegalMailNoticeServiceInterface::class)->entity()->name);
    }

    public function test_the_sender_name_falls_back_to_the_site_name(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('mail_driver', 'log');
        $settings->set('mail_from_address', 'info@example.com');
        $settings->set('mail_from_name', '');

        app(MailConfigurationServiceInterface::class)->applyToRuntime();

        $this->assertSame(self::SITE_NAME, config('mail.from.name'));
    }

    public function test_a_sender_name_of_its_own_wins_over_the_site_name(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('mail_driver', 'log');
        $settings->set('mail_from_address', 'info@example.com');
        $settings->set('mail_from_name', 'Secretaría');

        app(MailConfigurationServiceInterface::class)->applyToRuntime();

        $this->assertSame('Secretaría', config('mail.from.name'));
    }

    public function test_the_admin_panel_brand_uses_the_site_name(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertSee(self::SITE_NAME)
            ->assertDontSee(self::INSTALLATION_NAME);
    }
}
