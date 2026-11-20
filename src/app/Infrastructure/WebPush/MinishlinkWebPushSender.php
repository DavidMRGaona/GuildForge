<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

use App\Application\WebPush\DTOs\VapidKeysDTO;
use App\Application\WebPush\DTOs\WebPushSubscriptionDTO;
use App\Application\WebPush\Services\WebPushConfigurationInterface;
use App\Application\WebPush\Services\WebPushSenderInterface;
use App\Domain\WebPush\WebPushDeliveryOutcome;
use App\Domain\WebPush\WebPushEndpointRule;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Throwable;

/**
 * Sends through minishlink/web-push (core#803): payload encrypted with aes128gcm for the browser's
 * keys, signed with the site's VAPID keys. The endpoint is checked again before every message,
 * so a row written by other means never makes the site call another host.
 */
final readonly class MinishlinkWebPushSender implements WebPushSenderInterface
{
    private const int TIMEOUT_SECONDS = 10;

    public function __construct(
        private WebPushConfigurationInterface $configuration,
        private ?ClientInterface $client = null,
    ) {}

    public function send(WebPushSubscriptionDTO $subscription, string $payload): WebPushDeliveryOutcome
    {
        $vapid = $this->configuration->vapid();

        if (! $vapid instanceof VapidKeysDTO || ! $this->configuration->endpointRule()->allows($subscription->endpoint)) {
            return WebPushDeliveryOutcome::Failed;
        }

        try {
            $report = $this->webPush($vapid)->sendOneNotification(
                new Subscription($subscription->endpoint, $subscription->p256dh, $subscription->auth, ContentEncoding::aes128gcm),
                $payload,
            );
            $status = $report->getResponse()?->getStatusCode();
        } catch (Throwable) {
            // The exception may name the endpoint: neither it nor its message are logged
            $status = null;
            $report = null;
        }

        if ($report instanceof MessageSentReport && $report->isSuccess()) {
            return WebPushDeliveryOutcome::Delivered;
        }

        $outcome = match (true) {
            $status === 404, $status === 410 => WebPushDeliveryOutcome::Gone,
            $status === null, $status === 429, $status >= 500 => WebPushDeliveryOutcome::RetryLater,
            default => WebPushDeliveryOutcome::Failed,
        };

        if ($outcome !== WebPushDeliveryOutcome::Gone) {
            Log::warning('A push service did not take a message', [
                'service' => WebPushEndpointRule::serviceOf($subscription->endpoint),
                'status' => $status,
            ]);
        }

        return $outcome;
    }

    private function webPush(VapidKeysDTO $vapid): WebPush
    {
        $factory = new HttpFactory;

        return new WebPush(
            auth: ['VAPID' => ['subject' => $vapid->subject, 'publicKey' => $vapid->publicKey, 'privateKey' => $vapid->privateKey]],
            defaultOptions: ['TTL' => (int) config('web-push.ttl', 86400), 'urgency' => 'normal', 'contentType' => 'application/json'],
            client: $this->client ?? new Client(['timeout' => self::TIMEOUT_SECONDS, 'allow_redirects' => false]),
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }
}
