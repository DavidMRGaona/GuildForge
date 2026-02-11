<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mail\Listeners;

use App\Application\Mail\Services\EmailQuotaServiceInterface;
use App\Infrastructure\Mail\Listeners\CheckQuotaBeforeSending;
use Illuminate\Mail\Events\MessageSending;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class CheckQuotaBeforeSendingTest extends TestCase
{
    private MockObject&EmailQuotaServiceInterface $quotaService;

    private CheckQuotaBeforeSending $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quotaService = $this->createMock(EmailQuotaServiceInterface::class);
        $this->listener = new CheckQuotaBeforeSending($this->quotaService);
    }

    public function test_it_allows_sending_when_quota_not_exceeded(): void
    {
        $this->quotaService->method('canSendEmail')
            ->willReturn(true);

        $event = $this->createMessageSendingEvent();

        $result = $this->listener->handle($event);

        $this->assertTrue($result);
    }

    public function test_it_blocks_sending_when_quota_exceeded(): void
    {
        $this->quotaService->method('canSendEmail')
            ->willReturn(false);

        $event = $this->createMessageSendingEvent();

        $result = $this->listener->handle($event);

        $this->assertFalse($result);
    }

    private function createMessageSendingEvent(): MessageSending
    {
        $email = new Email;
        $email->from(new Address('test@example.com'));
        $email->to(new Address('recipient@example.com'));
        $email->subject('Test');
        $email->text('Test body');

        return new MessageSending($email);
    }
}
