<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush\Jobs;

use App\Application\WebPush\DTOs\WebPushSubscriptionDTO;
use App\Application\WebPush\Services\WebPushConfigurationInterface;
use App\Application\WebPush\Services\WebPushSenderInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationRepositoryInterface;
use App\Domain\SiteNotifications\ValueObjects\StoredSiteNotice;
use App\Domain\ValueObjects\UserId;
use App\Domain\WebPush\WebPushDeliveryOutcome;
use App\Infrastructure\WebPush\WebPushRetryLaterException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one notice to one browser (core#803). The message carries `{"id": "<notice id>"}` and
 * nothing else: the service worker reads the notice with the person's session, which checks it
 * again. Decided when it runs: a notice read or hidden meanwhile, a subscription gone or push
 * turned off send nothing.
 */
final class SendWebPushJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $noticeId,
    ) {
        $this->afterCommit = true;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(
        WebPushConfigurationInterface $configuration,
        WebPushSubscriptionServiceInterface $subscriptions,
        WebPushSenderInterface $sender,
        SiteNotificationRepositoryInterface $notices,
    ): void {
        if (! $configuration->enabled()) {
            return;
        }

        $subscription = $subscriptions->find($this->subscriptionId);

        if (! $subscription instanceof WebPushSubscriptionDTO || ! $this->stillUnread($notices, $subscription)) {
            return;
        }

        $outcome = $sender->send($subscription, json_encode(['id' => $this->noticeId], JSON_THROW_ON_ERROR));

        match ($outcome) {
            WebPushDeliveryOutcome::Delivered => $subscriptions->markUsed($subscription->id),
            WebPushDeliveryOutcome::Gone => $subscriptions->forget($subscription->id),
            WebPushDeliveryOutcome::RetryLater => throw WebPushRetryLaterException::make(),
            WebPushDeliveryOutcome::Failed => null,
        };
    }

    private function stillUnread(SiteNotificationRepositoryInterface $notices, WebPushSubscriptionDTO $subscription): bool
    {
        $notice = $notices->findVisible(UserId::fromString($subscription->userId), $this->noticeId);

        return $notice instanceof StoredSiteNotice && $notice->isUnread();
    }
}
