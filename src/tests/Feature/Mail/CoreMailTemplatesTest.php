<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Mail\ContactFormMail;
use App\Mail\TestMail;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CoreMailTemplatesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        config(['app.name' => 'Asociación Tolerol']);
        Storage::fake('images');
    }

    public function test_mail_header_shows_the_email_logo_when_configured(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_email', 'settings/email-logo.png');

        $html = (string) (new MailMessage)->line('Contenido.')->render();

        $this->assertStringContainsString('<img src="'.Storage::disk('images')->url('settings/email-logo.png').'"', $html);
        $this->assertStringContainsString('alt="Asociación Tolerol"', $html);
    }

    public function test_mail_header_falls_back_to_the_light_logo(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_light', 'settings/logo.png');

        $html = (string) (new MailMessage)->line('Contenido.')->render();

        $this->assertStringContainsString('<img src="'.Storage::disk('images')->url('settings/logo.png').'"', $html);
    }

    public function test_mail_header_shows_the_site_name_without_a_logo(): void
    {
        $html = (string) (new MailMessage)->line('Contenido.')->render();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('Asociación Tolerol', $html);
        $this->assertStringNotContainsString('laravel.com', $html);
    }

    public function test_password_reset_email_is_in_spanish(): void
    {
        $user = UserModel::factory()->create();

        $message = (new ResetPassword('token-de-prueba'))->toMail($user);
        $html = (string) $message->render();

        $this->assertSame('Restablece tu contraseña', $message->subject);
        $this->assertStringContainsString('Recibes este correo porque hemos recibido una solicitud para restablecer la contraseña de tu cuenta.', $html);
        $this->assertStringContainsString('Restablecer contraseña', $html);
        $this->assertStringContainsString('Este enlace caduca en 60 minutos.', $html);
        $this->assertStringContainsString('Si no has solicitado restablecer la contraseña, puedes ignorar este correo.', $html);
        $this->assertStringContainsString('/restablecer-contrasena/token-de-prueba', $html);
        $this->assertStringNotContainsString('password reset', $html);
    }

    public function test_email_verification_email_is_in_spanish(): void
    {
        $user = UserModel::factory()->unverified()->create();

        $message = (new VerifyEmail)->toMail($user);
        $html = (string) $message->render();

        $this->assertSame('Verifica tu correo electrónico', $message->subject);
        $this->assertStringContainsString('Pulsa el botón para verificar tu dirección de correo electrónico.', $html);
        $this->assertStringContainsString('Verificar correo electrónico', $html);
        $this->assertStringContainsString('Si no has creado una cuenta, puedes ignorar este correo.', $html);
        $this->assertStringNotContainsString('verify your email', $html);
    }

    public function test_welcome_email_greets_with_a_comma(): void
    {
        $user = UserModel::factory()->create(['name' => 'Ana']);

        $html = (string) (new WelcomeNotification('Asociación Tolerol'))->toMail($user)->render();

        $this->assertStringContainsString('¡Hola, Ana!', $html);
    }

    public function test_contact_form_email_uses_translations_and_the_site_name(): void
    {
        $mail = new ContactFormMail('Ana López', 'ana@example.com', 'Quiero apuntarme a la próxima partida.');

        $html = $mail->render();

        $this->assertStringContainsString('Nombre', $html);
        $this->assertStringContainsString('Mensaje', $html);
        $this->assertStringContainsString('Ana López', $html);
        $this->assertStringContainsString('mailto:ana@example.com', $html);
        $this->assertStringContainsString('Quiero apuntarme a la próxima partida.', $html);
        $this->assertStringContainsString('Este mensaje se ha enviado desde el formulario de contacto de Asociación Tolerol.', $html);
        $this->assertStringNotContainsString('GuildForge', $html);
    }

    public function test_contact_form_email_escapes_the_message(): void
    {
        $mail = new ContactFormMail('<b>Ana</b>', 'ana@example.com', '<script>alert(1)</script>');

        $html = $mail->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>Ana</b>', $html);
    }

    public function test_test_email_uses_translations_and_the_site_name(): void
    {
        $mail = new TestMail('smtp', '19/10/2026 18:00');

        $html = $mail->render();

        $this->assertSame('Asociación Tolerol: email de prueba', $mail->envelope()->subject);
        $this->assertStringContainsString('Si has recibido este correo, la configuración de correo funciona correctamente.', $html);
        $this->assertStringContainsString('smtp', $html);
        $this->assertStringContainsString('19/10/2026 18:00', $html);
        $this->assertStringNotContainsString('GuildForge', $html);
    }
}
