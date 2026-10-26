<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mail\Services;

use App\Infrastructure\Mail\Services\MailTestService;
use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(MailTestService::class)]
final class MailTestServiceTest extends TestCase
{
    private MailTestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MailTestService;
    }

    public function test_send_test_email_returns_success_dto(): void
    {
        Mail::fake();

        $result = $this->service->sendTestEmail('test@example.com');

        $this->assertTrue($result->success);
        $this->assertNull($result->errorMessage);
        $this->assertNotNull($result->responseTimeMs);

        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('test@example.com'));
    }

    public function test_send_test_email_returns_failure_on_exception(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with('bad@example.com')
            ->andThrow(new \RuntimeException('SMTP connection refused'));

        $result = $this->service->sendTestEmail('bad@example.com');

        $this->assertFalse($result->success);
        $this->assertNotNull($result->errorMessage);
        $this->assertStringContainsString('SMTP connection refused', $result->errorMessage);
        $this->assertNotNull($result->responseTimeMs);
    }

    public function test_test_smtp_connection_returns_failure_when_the_server_refuses_the_connection(): void
    {
        // Nothing listens on port 1, so the connection is refused at once
        config()->set('mail.mailers.smtp.host', '127.0.0.1');
        config()->set('mail.mailers.smtp.port', 1);

        $result = $this->service->testSmtpConnection();

        $this->assertFalse($result->success);
        $this->assertNotNull($result->errorMessage);
        $this->assertStringContainsString('Connection failed', $result->errorMessage);
        $this->assertNotNull($result->responseTimeMs);
    }

    public function test_test_smtp_connection_returns_failure_when_no_host_configured(): void
    {
        config()->set('mail.mailers.smtp.host', '');

        $result = $this->service->testSmtpConnection();

        $this->assertFalse($result->success);
        $this->assertNotNull($result->errorMessage);
        $this->assertStringContainsString('No SMTP host configured', $result->errorMessage);
    }

    public function test_send_test_email_measures_response_time(): void
    {
        Mail::fake();

        $result = $this->service->sendTestEmail('test@example.com');

        $this->assertIsInt($result->responseTimeMs);
        $this->assertGreaterThanOrEqual(0, $result->responseTimeMs);
    }
}
