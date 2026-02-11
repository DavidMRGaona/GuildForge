<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mail\Ses;

use App\Infrastructure\Mail\Ses\SnsMessageValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(SnsMessageValidator::class)]
final class SnsMessageValidatorTest extends TestCase
{
    private SnsMessageValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new SnsMessageValidator;
    }

    public function test_returns_false_when_type_is_missing(): void
    {
        $payload = $this->makeBasePayload();
        unset($payload['Type']);

        $this->assertFalse($this->validator->isValid($payload));
    }

    public function test_returns_false_when_signature_version_is_missing(): void
    {
        $payload = $this->makeBasePayload();
        unset($payload['SignatureVersion']);

        $this->assertFalse($this->validator->isValid($payload));
    }

    public function test_returns_false_when_signature_is_missing(): void
    {
        $payload = $this->makeBasePayload();
        unset($payload['Signature']);

        $this->assertFalse($this->validator->isValid($payload));
    }

    public function test_returns_false_when_signing_cert_url_is_missing(): void
    {
        $payload = $this->makeBasePayload();
        unset($payload['SigningCertURL']);

        $this->assertFalse($this->validator->isValid($payload));
    }

    public function test_returns_false_for_unsupported_signature_version(): void
    {
        $payload = $this->makeBasePayload(['SignatureVersion' => '2']);

        $this->assertFalse($this->validator->isValid($payload));
    }

    public function test_returns_false_for_http_signing_cert_url(): void
    {
        $payload = $this->makeBasePayload([
            'SigningCertURL' => 'http://sns.us-east-1.amazonaws.com/SimpleNotificationService-cert.pem',
        ]);

        $this->assertFalse($this->validator->isValid($payload));
    }

    public function test_returns_false_for_non_amazonaws_domain(): void
    {
        $payload = $this->makeBasePayload([
            'SigningCertURL' => 'https://evil.com/cert.pem',
        ]);

        $this->assertFalse($this->validator->isValid($payload));
    }

    public function test_returns_false_for_amazonaws_subdomain_spoofing(): void
    {
        $payload = $this->makeBasePayload([
            'SigningCertURL' => 'https://amazonaws.com.evil.com/cert.pem',
        ]);

        $this->assertFalse($this->validator->isValid($payload));
    }

    public function test_returns_false_for_empty_payload(): void
    {
        $this->assertFalse($this->validator->isValid([]));
    }

    #[DataProvider('invalidCertUrlProvider')]
    public function test_returns_false_for_invalid_cert_urls(string $url): void
    {
        $payload = $this->makeBasePayload(['SigningCertURL' => $url]);

        $this->assertFalse($this->validator->isValid($payload));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCertUrlProvider(): array
    {
        return [
            'ftp scheme' => ['ftp://sns.us-east-1.amazonaws.com/cert.pem'],
            'no scheme' => ['sns.us-east-1.amazonaws.com/cert.pem'],
            'contains amazonaws but wrong domain' => ['https://not-amazonaws.com/cert.pem'],
            'amazonaws in path not host' => ['https://evil.com/amazonaws.com/cert.pem'],
            'empty string' => [''],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function makeBasePayload(array $overrides = []): array
    {
        return array_merge([
            'Type' => 'Notification',
            'MessageId' => 'test-message-id-uuid',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:test-topic',
            'Message' => 'test message body',
            'Timestamp' => '2025-01-01T00:00:00.000Z',
            'SignatureVersion' => '1',
            'Signature' => base64_encode('fake-signature'),
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-cert.pem',
        ], $overrides);
    }
}
