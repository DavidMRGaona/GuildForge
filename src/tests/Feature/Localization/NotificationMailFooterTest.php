<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

final class NotificationMailFooterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
    }

    private function message(): MailMessage
    {
        return (new MailMessage)
            ->line('Contenido del aviso.')
            ->action('Ver mesa', 'https://example.test/mesa');
    }

    public function test_html_mail_footer_is_in_spanish(): void
    {
        $html = (string) $this->message()->render();

        $this->assertStringContainsString('¡Hola!', $html);
        $this->assertStringContainsString('Saludos,', $html);
        $this->assertStringContainsString('Si no puedes pulsar el botón «Ver mesa», copia y pega esta dirección en tu navegador:', $html);
        $this->assertStringContainsString('Todos los derechos reservados.', $html);
        $this->assertStringNotContainsString('Regards,', $html);
        $this->assertStringNotContainsString('Hello!', $html);
        $this->assertStringNotContainsString('trouble clicking', $html);
        $this->assertStringNotContainsString('All rights reserved.', $html);
    }

    public function test_text_mail_footer_is_in_spanish(): void
    {
        $text = (string) app(Markdown::class)->renderText('notifications::email', $this->message()->data());

        $this->assertStringContainsString('¡Hola!', $text);
        $this->assertStringContainsString('Saludos,', $text);
        $this->assertStringContainsString('Si no puedes pulsar el botón «Ver mesa», copia y pega esta dirección en tu navegador:', $text);
        $this->assertStringNotContainsString('Regards,', $text);
        $this->assertStringNotContainsString('trouble clicking', $text);
    }

    public function test_error_level_mail_greets_in_spanish(): void
    {
        $html = (string) $this->message()->level('error')->render();

        $this->assertStringContainsString('¡Vaya!', $html);
        $this->assertStringNotContainsString('Whoops!', $html);
    }
}
