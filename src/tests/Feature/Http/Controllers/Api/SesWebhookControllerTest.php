<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Mail\Enums\EmailStatus;
use App\Domain\Mail\Events\SesBounceReceived;
use App\Domain\Mail\Events\SesComplaintReceived;
use App\Infrastructure\Mail\Ses\SnsMessageValidatorInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EmailLogModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SesWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function subscriptionConfirmationPayload(): array
    {
        return [
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'test-message-id',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789:ses-notifications',
            'Message' => 'You have chosen to subscribe to the topic.',
            'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription&TopicArn=arn:aws:sns:us-east-1:123456789:ses-notifications&Token=test-token',
            'Timestamp' => '2025-01-01T00:00:00.000Z',
            'SignatureVersion' => '1',
            'Signature' => 'base64signature',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        ];
    }

    private function bounceNotificationPayload(string $messageId = 'ses-message-id'): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => 'test-notification-id',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789:ses-notifications',
            'Message' => json_encode([
                'notificationType' => 'Bounce',
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bouncedRecipients' => [
                        ['emailAddress' => 'bounce@example.com'],
                    ],
                ],
                'mail' => [
                    'messageId' => $messageId,
                ],
            ]),
            'Timestamp' => '2025-01-01T00:00:00.000Z',
            'SignatureVersion' => '1',
            'Signature' => 'base64signature',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        ];
    }

    private function complaintNotificationPayload(string $messageId = 'ses-message-id'): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => 'test-notification-id',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789:ses-notifications',
            'Message' => json_encode([
                'notificationType' => 'Complaint',
                'complaint' => [
                    'complainedRecipients' => [
                        ['emailAddress' => 'complaint@example.com'],
                    ],
                ],
                'mail' => [
                    'messageId' => $messageId,
                ],
            ]),
            'Timestamp' => '2025-01-01T00:00:00.000Z',
            'SignatureVersion' => '1',
            'Signature' => 'base64signature',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        ];
    }

    private function deliveryNotificationPayload(string $messageId = 'ses-message-id'): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => 'test-notification-id',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789:ses-notifications',
            'Message' => json_encode([
                'notificationType' => 'Delivery',
                'delivery' => [
                    'recipients' => ['delivered@example.com'],
                    'timestamp' => '2025-01-01T00:00:00.000Z',
                ],
                'mail' => [
                    'messageId' => $messageId,
                ],
            ]),
            'Timestamp' => '2025-01-01T00:00:00.000Z',
            'SignatureVersion' => '1',
            'Signature' => 'base64signature',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        ];
    }

    private function mockValidatorReturning(bool $isValid): void
    {
        $this->mock(SnsMessageValidatorInterface::class, function ($mock) use ($isValid): void {
            $mock->shouldReceive('isValid')->once()->andReturn($isValid);
        });
    }

    public function test_rejects_request_with_invalid_signature(): void
    {
        $this->mockValidatorReturning(false);

        $response = $this->postJson('/api/webhooks/ses', $this->bounceNotificationPayload());

        $response->assertStatus(403);
    }

    public function test_handles_subscription_confirmation(): void
    {
        $this->mockValidatorReturning(true);

        $payload = $this->subscriptionConfirmationPayload();

        Http::fake([
            $payload['SubscribeURL'] => Http::response('OK', 200),
        ]);

        $response = $this->postJson('/api/webhooks/ses', $payload);

        $response->assertStatus(200);
        Http::assertSent(function ($request) use ($payload): bool {
            return $request->url() === $payload['SubscribeURL'];
        });
    }

    public function test_handles_bounce_notification(): void
    {
        Event::fake([SesBounceReceived::class]);

        $this->mockValidatorReturning(true);

        $emailLog = EmailLogModel::factory()->create([
            'message_id' => 'ses-message-id',
            'status' => EmailStatus::Sent,
        ]);

        $response = $this->postJson('/api/webhooks/ses', $this->bounceNotificationPayload());

        $response->assertStatus(200);

        $emailLog->refresh();
        $this->assertEquals(EmailStatus::Bounced, $emailLog->status);

        Event::assertDispatched(SesBounceReceived::class);
    }

    public function test_handles_complaint_notification(): void
    {
        Event::fake([SesComplaintReceived::class]);

        $this->mockValidatorReturning(true);

        $emailLog = EmailLogModel::factory()->create([
            'message_id' => 'ses-message-id',
            'status' => EmailStatus::Sent,
        ]);

        $response = $this->postJson('/api/webhooks/ses', $this->complaintNotificationPayload());

        $response->assertStatus(200);

        $emailLog->refresh();
        $this->assertEquals(EmailStatus::Complained, $emailLog->status);

        Event::assertDispatched(SesComplaintReceived::class);
    }

    public function test_returns_200_for_delivery_notification(): void
    {
        $this->mockValidatorReturning(true);

        $emailLog = EmailLogModel::factory()->create([
            'message_id' => 'ses-message-id',
            'status' => EmailStatus::Sent,
        ]);

        $response = $this->postJson('/api/webhooks/ses', $this->deliveryNotificationPayload());

        $response->assertStatus(200);

        $emailLog->refresh();
        $this->assertEquals(EmailStatus::Sent, $emailLog->status);
    }

    public function test_returns_400_for_invalid_json(): void
    {
        $response = $this->call(
            'POST',
            '/api/webhooks/ses',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'not-valid-json{{'
        );

        $response->assertStatus(400);
    }

    public function test_returns_200_when_email_log_not_found(): void
    {
        Event::fake([SesBounceReceived::class]);

        $this->mockValidatorReturning(true);

        $response = $this->postJson('/api/webhooks/ses', $this->bounceNotificationPayload('unknown-message-id'));

        $response->assertStatus(200);
    }
}
