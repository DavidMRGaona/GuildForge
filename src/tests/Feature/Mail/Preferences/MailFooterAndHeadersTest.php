<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Preferences;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\Support\Mail\CategorizedTestNotification;
use Tests\Support\Mail\UncategorizedTestNotification;
use Tests\TestCase;

/**
 * Every mail to an account says what can be chosen; optional ones also carry the RFC 8058 headers.
 */
final class MailFooterAndHeadersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string OPTIONAL_QUESTION = '¿No quieres recibir más correos como este?';

    private const string MANDATORY_TEXT = 'Este correo siempre se envía.';

    private const string NEUTRAL_TEXT = 'Puedes elegir qué correos recibes';

    /**
     * @var list<Email>
     */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');

        $this->app->make(MailCategoryRegistryInterface::class)->registerMany([
            MailCategoryDTO::mandatory(
                key: 'example.confirmations',
                section: 'Ejemplo',
                label: 'Confirmaciones',
                description: 'Lo que has hecho',
                reason: 'Confirma algo que has hecho',
            ),
            MailCategoryDTO::optional(
                key: 'example.reminders',
                section: 'Ejemplo',
                label: 'Recordatorios',
                description: 'Antes de empezar',
            ),
            MailCategoryDTO::optional(
                key: 'example.styled',
                section: 'Ejemplo',
                label: 'Avisos *urgentes* [aquí](https://example.org)',
                description: 'Con Markdown en el nombre',
            ),
        ], 'example');

        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $this->sent[] = $event->message;
        });
    }

    public function test_an_optional_mail_offers_to_unsubscribe_and_to_choose(): void
    {
        $user = UserModel::factory()->create();
        $links = $this->app->make(MailUnsubscribeLinksInterface::class);

        $user->notify(new CategorizedTestNotification('example.reminders'));

        $mail = $this->onlyMail();
        $html = (string) $mail->getHtmlBody();
        $this->assertStringContainsString(self::OPTIONAL_QUESTION, $html);
        $this->assertStringContainsString('Darte de baja de «Recordatorios»', $html);
        $this->assertStringContainsString('href="'.e($links->unsubscribeUrl($user->id, 'example.reminders')).'"', $html);
        $this->assertStringContainsString('Elegir qué correos recibes', $html);
        $this->assertStringContainsString('href="'.e($links->preferencesUrl()).'"', $html);

        $text = (string) $mail->getTextBody();
        $this->assertStringContainsString(self::OPTIONAL_QUESTION, $text);
        $this->assertStringContainsString($links->unsubscribeUrl($user->id, 'example.reminders'), $text);
        $this->assertStringContainsString($links->preferencesUrl(), $text);
    }

    public function test_an_optional_mail_carries_the_one_click_headers(): void
    {
        $user = UserModel::factory()->create();
        $links = $this->app->make(MailUnsubscribeLinksInterface::class);

        $user->notify(new CategorizedTestNotification('example.reminders'));

        $headers = $this->onlyMail()->getHeaders();
        $this->assertSame('<'.$links->oneClickUrl($user->id, 'example.reminders').'>', $headers->get('List-Unsubscribe')?->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $headers->get('List-Unsubscribe-Post')?->getBodyAsString());
    }

    public function test_a_mandatory_mail_says_it_is_always_sent_and_has_no_headers(): void
    {
        $user = UserModel::factory()->create();
        $links = $this->app->make(MailUnsubscribeLinksInterface::class);

        $user->notify(new CategorizedTestNotification('example.confirmations'));

        $mail = $this->onlyMail();
        $html = (string) $mail->getHtmlBody();
        $this->assertStringContainsString(self::MANDATORY_TEXT, $html);
        $this->assertStringContainsString('href="'.e($links->preferencesUrl()).'"', $html);
        $this->assertStringNotContainsString(self::OPTIONAL_QUESTION, $html);
        $this->assertStringContainsString(self::MANDATORY_TEXT, (string) $mail->getTextBody());
        $this->assertFalse($mail->getHeaders()->has('List-Unsubscribe'));
        $this->assertFalse($mail->getHeaders()->has('List-Unsubscribe-Post'));
    }

    public function test_a_mail_without_a_category_only_points_to_the_preferences(): void
    {
        $user = UserModel::factory()->create();

        $user->notify(new UncategorizedTestNotification);

        $mail = $this->onlyMail();
        $html = (string) $mail->getHtmlBody();
        $this->assertStringContainsString(self::NEUTRAL_TEXT, $html);
        $this->assertStringNotContainsString(self::OPTIONAL_QUESTION, $html);
        $this->assertStringNotContainsString(self::MANDATORY_TEXT, $html);
        $this->assertFalse($mail->getHeaders()->has('List-Unsubscribe'));
    }

    public function test_a_mail_to_a_plain_address_has_no_footer_nor_headers(): void
    {
        Notification::route('mail', 'guest@example.com')->notify(new CategorizedTestNotification('example.reminders'));

        $mail = $this->onlyMail();
        $html = (string) $mail->getHtmlBody();
        $this->assertStringNotContainsString(self::OPTIONAL_QUESTION, $html);
        $this->assertStringNotContainsString(self::MANDATORY_TEXT, $html);
        $this->assertStringNotContainsString(self::NEUTRAL_TEXT, $html);
        $this->assertStringNotContainsString('/correos/baja/', $html);
        $this->assertFalse($mail->getHeaders()->has('List-Unsubscribe'));
    }

    public function test_the_footer_does_not_leak_into_the_next_mail(): void
    {
        UserModel::factory()->create()->notify(new CategorizedTestNotification('example.reminders'));

        $html = (string) (new MailMessage)->line('Contenido.')->render();

        $this->assertStringNotContainsString(self::OPTIONAL_QUESTION, $html);
        $this->assertStringNotContainsString(self::NEUTRAL_TEXT, $html);
    }

    public function test_the_category_name_cannot_add_markdown_to_the_footer(): void
    {
        UserModel::factory()->create()->notify(new CategorizedTestNotification('example.styled'));

        $html = (string) $this->onlyMail()->getHtmlBody();

        $this->assertStringNotContainsString('<em>urgentes</em>', $html);
        $this->assertStringNotContainsString('href="https://example.org"', $html);
        $this->assertStringContainsString('urgentes', $html);
    }

    public function test_the_welcome_mail_says_mail_can_be_chosen_in_the_profile(): void
    {
        $user = UserModel::factory()->create();

        $html = (string) (new WelcomeNotification('Asociación de ejemplo'))->toMail($user)->render();

        $this->assertStringContainsString('En tu perfil puedes elegir qué correos quieres recibir.', $html);
    }

    private function onlyMail(): Email
    {
        $this->assertCount(1, $this->sent);

        return $this->sent[0];
    }
}
