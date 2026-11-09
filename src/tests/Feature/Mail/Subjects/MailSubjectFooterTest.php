<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Subjects;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\Support\Mail\SubjectTestNotification;
use Tests\TestCase;

/**
 * An optional mail about one subject offers to stop the notices of that subject in its footer.
 */
final class MailSubjectFooterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string MUTE_TEXT = 'No recibir más avisos de «La tumba»';

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
        ], 'example');

        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $this->sent[] = $event->message;
        });
    }

    public function test_an_optional_mail_about_a_subject_offers_to_mute_it(): void
    {
        $user = UserModel::factory()->create();
        $links = $this->app->make(MailUnsubscribeLinksInterface::class);

        $user->notify(new SubjectTestNotification('example.reminders', $this->campaign()));

        $mail = $this->onlyMail();
        $url = $links->muteSubjectUrl($user->id, $this->campaign());
        $html = (string) $mail->getHtmlBody();
        $this->assertStringContainsString(self::MUTE_TEXT, $html);
        $this->assertStringContainsString('href="'.e($url).'"', $html);
        // The category unsubscribe stays next to it
        $this->assertStringContainsString('Darte de baja de «Recordatorios»', $html);

        $text = (string) $mail->getTextBody();
        $this->assertStringContainsString(self::MUTE_TEXT.': '.$url, $text);
    }

    public function test_the_one_click_header_still_unsubscribes_from_the_category(): void
    {
        $user = UserModel::factory()->create();
        $links = $this->app->make(MailUnsubscribeLinksInterface::class);

        $user->notify(new SubjectTestNotification('example.reminders', $this->campaign()));

        $this->assertSame(
            '<'.$links->oneClickUrl($user->id, 'example.reminders').'>',
            $this->onlyMail()->getHeaders()->get('List-Unsubscribe')?->getBodyAsString(),
        );
    }

    public function test_a_mandatory_mail_about_a_subject_does_not_offer_to_mute_it(): void
    {
        UserModel::factory()->create()->notify(new SubjectTestNotification('example.confirmations', $this->campaign()));

        $html = (string) $this->onlyMail()->getHtmlBody();
        $this->assertStringNotContainsString('No recibir más avisos', $html);
        $this->assertStringNotContainsString('/correos/silenciar/', $html);
    }

    public function test_a_mail_without_a_subject_or_whose_subject_fails_does_not_offer_it(): void
    {
        $user = UserModel::factory()->create();

        $user->notify(new SubjectTestNotification('example.reminders', null));
        $user->notify(new SubjectTestNotification('example.reminders', $this->campaign(), failing: true));

        $this->assertCount(2, $this->sent);
        foreach ($this->sent as $mail) {
            $this->assertStringNotContainsString('No recibir más avisos', (string) $mail->getHtmlBody());
            // The rest of the footer is still there
            $this->assertStringContainsString('Darte de baja de «Recordatorios»', (string) $mail->getHtmlBody());
        }
    }

    public function test_a_mail_to_a_plain_address_does_not_offer_it(): void
    {
        Notification::route('mail', 'guest@example.com')->notify(new SubjectTestNotification('example.reminders', $this->campaign()));

        $this->assertStringNotContainsString('/correos/silenciar/', (string) $this->onlyMail()->getHtmlBody());
    }

    public function test_the_subject_name_cannot_add_markdown_or_html_to_the_footer(): void
    {
        $subject = new MailSubjectDTO('example.campaign', 'c-1', 'Partida *épica* [aquí](https://example.org) <b>ya</b>');

        UserModel::factory()->create()->notify(new SubjectTestNotification('example.reminders', $subject));

        $html = (string) $this->onlyMail()->getHtmlBody();
        $this->assertStringNotContainsString('<em>épica</em>', $html);
        $this->assertStringNotContainsString('href="https://example.org"', $html);
        $this->assertStringNotContainsString('<b>ya</b>', $html);
        $this->assertStringContainsString('épica', $html);
    }

    private function campaign(): MailSubjectDTO
    {
        return new MailSubjectDTO('example.campaign', 'c-1', 'La tumba', 'example::mail.subjects.campaign');
    }

    private function onlyMail(): Email
    {
        $this->assertCount(1, $this->sent);

        return $this->sent[0];
    }
}
