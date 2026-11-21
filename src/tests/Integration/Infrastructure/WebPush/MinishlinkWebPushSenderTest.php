<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\WebPush;

use App\Application\WebPush\DTOs\WebPushSubscriptionDTO;
use App\Application\WebPush\Services\WebPushConfigurationInterface;
use App\Domain\WebPush\WebPushDeliveryOutcome;
use App\Infrastructure\WebPush\MinishlinkWebPushSender;
use ArrayObject;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Tests\Support\WebPush\EnablesWebPush;
use Tests\TestCase;

final class MinishlinkWebPushSenderTest extends TestCase
{
    use EnablesWebPush;

    /** @var ArrayObject<int, mixed> Guzzle's history: one entry per request sent */
    private ArrayObject $history;

    protected function setUp(): void
    {
        parent::setUp();

        $this->history = new ArrayObject;
        $this->enableWebPush();
    }

    public function test_the_message_is_encrypted_for_the_browser_and_signed_for_the_site(): void
    {
        $outcome = $this->sender(new Response(201))->send($this->subscription(), '{"id":"abc"}');

        $this->assertSame(WebPushDeliveryOutcome::Delivered, $outcome);
        $entry = $this->history[0] ?? null;
        $this->assertIsArray($entry);
        $request = $entry['request'] ?? null;
        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::ENDPOINT, (string) $request->getUri());
        $this->assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
        $this->assertSame('86400', $request->getHeaderLine('TTL'));
        $this->assertStringStartsWith('vapid t=', $request->getHeaderLine('Authorization'));
        $this->assertStringContainsString('k='.self::VAPID_PUBLIC_KEY, $request->getHeaderLine('Authorization'));
        // Encrypted: the notice id never travels in the clear
        $this->assertStringNotContainsString('abc', (string) $request->getBody());
    }

    /**
     * @return array<string, array{int, WebPushDeliveryOutcome}>
     */
    public static function answers(): array
    {
        return [
            'gone' => [410, WebPushDeliveryOutcome::Gone],
            'not found' => [404, WebPushDeliveryOutcome::Gone],
            'too many requests' => [429, WebPushDeliveryOutcome::RetryLater],
            'server error' => [503, WebPushDeliveryOutcome::RetryLater],
            'refused' => [400, WebPushDeliveryOutcome::Failed],
            'too large' => [413, WebPushDeliveryOutcome::Failed],
        ];
    }

    #[DataProvider('answers')]
    public function test_the_answer_of_the_push_service_decides_what_happens_next(int $status, WebPushDeliveryOutcome $expected): void
    {
        $this->assertSame($expected, $this->sender(new Response($status))->send($this->subscription(), '{"id":"abc"}'));
    }

    public function test_a_network_failure_is_tried_again_later(): void
    {
        $outcome = $this->sender(new ConnectException('down', new Request('POST', self::ENDPOINT)))
            ->send($this->subscription(), '{"id":"abc"}');

        $this->assertSame(WebPushDeliveryOutcome::RetryLater, $outcome);
    }

    public function test_a_subscription_whose_keys_cannot_encrypt_is_unusable_and_nothing_is_sent(): void
    {
        $subscription = new WebPushSubscriptionDTO(
            id: 'sub-1',
            userId: 'user-1',
            endpoint: self::ENDPOINT,
            p256dh: 'BAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            auth: self::BROWSER_AUTH,
            browser: 'chrome',
            platform: 'android',
            createdAt: new DateTimeImmutable,
            lastUsedAt: null,
        );

        $this->assertSame(WebPushDeliveryOutcome::Unusable, $this->sender(new Response(201))->send($subscription, '{"id":"abc"}'));
        $this->assertCount(0, $this->history);
    }

    public function test_failures_are_logged_without_the_endpoint_or_the_keys(): void
    {
        $log = Log::spy();

        $this->sender(new Response(400, [], 'invalid token for '.self::ENDPOINT))->send($this->subscription(), '{"id":"abc"}');

        $log->shouldHaveReceived('warning')->once()->withArgs(static function (string $message, array $context): bool {
            $flat = json_encode($context, JSON_THROW_ON_ERROR);

            return $context === ['service' => 'fcm.googleapis.com', 'status' => 400]
                && ! str_contains($flat, 'example-device-token')
                && ! str_contains($message, 'example-device-token');
        });
    }

    public function test_nothing_is_sent_while_push_is_off(): void
    {
        config(['web-push.private_key' => null]);

        $this->assertSame(WebPushDeliveryOutcome::Failed, $this->sender(new Response(201))->send($this->subscription(), '{}'));
        $this->assertCount(0, $this->history);
    }

    private function sender(Response|ConnectException $answer): MinishlinkWebPushSender
    {
        $stack = HandlerStack::create(new MockHandler([$answer]));
        // Guzzle takes the container by reference; the object is the same one the tests read
        $history = $this->history;
        $stack->push(Middleware::history($history));

        return new MinishlinkWebPushSender(app(WebPushConfigurationInterface::class), new Client(['handler' => $stack]));
    }

    private function subscription(): WebPushSubscriptionDTO
    {
        return new WebPushSubscriptionDTO(
            id: 'sub-1',
            userId: 'user-1',
            endpoint: self::ENDPOINT,
            p256dh: self::BROWSER_P256DH,
            auth: self::BROWSER_AUTH,
            browser: 'chrome',
            platform: 'android',
            createdAt: new DateTimeImmutable,
            lastUsedAt: null,
        );
    }
}
