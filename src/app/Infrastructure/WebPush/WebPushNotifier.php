<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\WebPush\Services\WebPushConfigurationInterface;
use App\Application\WebPush\Services\WebPushNotifierInterface;
use App\Application\WebPush\Services\WebPushPreferenceInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Infrastructure\WebPush\Jobs\SendWebPushJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One queued job per browser of the account, each sending the notice's id only (core#803).
 * While impersonating nothing goes out: the safety net drops queued jobs anyway, and this
 * says so on purpose.
 */
final readonly class WebPushNotifier implements WebPushNotifierInterface
{
    public function __construct(
        private WebPushConfigurationInterface $configuration,
        private WebPushPreferenceInterface $preferences,
        private WebPushSubscriptionServiceInterface $subscriptions,
        private ImpersonationContextInterface $impersonation,
    ) {}

    public function notify(string $userId, string $noticeId, MailCategoryDTO $category, ?MailSubjectDTO $subject): void
    {
        try {
            if (! $this->configuration->enabled()
                || $this->impersonation->isActive()
                || ! $this->preferences->wantsPush($userId, $category, $subject)) {
                return;
            }

            foreach ($this->subscriptions->forUser($userId) as $subscription) {
                SendWebPushJob::dispatch($subscription->id, $noticeId);
            }
        } catch (Throwable $exception) {
            // The notice is in the bell already: a push problem never costs it
            Log::warning('A notice could not be queued for push', [
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);
        }
    }
}
