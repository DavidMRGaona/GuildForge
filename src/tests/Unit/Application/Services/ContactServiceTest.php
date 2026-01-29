<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\DTOs\ContactMessageDTO;
use App\Application\Services\ContactServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Services\ContactService;
use App\Mail\ContactFormMail;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(ContactService::class)]
final class ContactServiceTest extends TestCase
{
    private MockObject&SettingsServiceInterface $settings;

    private ContactServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = $this->createMock(SettingsServiceInterface::class);
        $this->service = new ContactService($this->settings);
    }

    #[Test]
    public function it_reports_contact_email_as_configured_when_set(): void
    {
        $this->settings->method('get')
            ->with('contact_email', '')
            ->willReturn('admin@example.com');

        $this->assertTrue($this->service->isContactEmailConfigured());
    }

    #[Test]
    public function it_reports_contact_email_as_not_configured_when_empty(): void
    {
        $this->settings->method('get')
            ->with('contact_email', '')
            ->willReturn('');

        $this->assertFalse($this->service->isContactEmailConfigured());
    }

    #[Test]
    public function it_sends_email_when_contact_email_is_configured(): void
    {
        Mail::fake();

        $this->settings->method('get')
            ->with('contact_email', '')
            ->willReturn('admin@example.com');

        $dto = new ContactMessageDTO(
            senderName: 'Juan García',
            senderEmail: 'juan@example.com',
            messageBody: 'Hola, quiero información.',
        );

        $result = $this->service->sendContactMessage($dto);

        $this->assertTrue($result);
        Mail::assertSent(ContactFormMail::class, function (ContactFormMail $mail) {
            return $mail->senderName === 'Juan García'
                && $mail->senderEmail === 'juan@example.com'
                && $mail->messageBody === 'Hola, quiero información.'
                && $mail->hasTo('admin@example.com');
        });
    }

    #[Test]
    public function it_does_not_send_email_when_contact_email_is_empty(): void
    {
        Mail::fake();

        $this->settings->method('get')
            ->with('contact_email', '')
            ->willReturn('');

        $dto = new ContactMessageDTO(
            senderName: 'Juan García',
            senderEmail: 'juan@example.com',
            messageBody: 'Hola, quiero información.',
        );

        $result = $this->service->sendContactMessage($dto);

        $this->assertFalse($result);
        Mail::assertNothingSent();
    }
}
