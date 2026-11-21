<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebPush;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\WebPush\DTOs\WebPushSubscriptionDTO;
use App\Application\WebPush\Services\WebPushSenderInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Domain\WebPush\WebPushDeliveryOutcome;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The devices of the account from the «Avisos» tab (core#804): removing one of them and sending a
 * test notice to the browser in use. Only the account's own devices: any other answers like an
 * unknown one. Refused while impersonating.
 */
final class WebPushDeviceController extends Controller
{
    /** What a test message carries: the service worker shows its own fixed text, nothing else travels. */
    public const string TEST_PAYLOAD = '{"test":true}';

    public function __construct(
        private readonly WebPushSubscriptionServiceInterface $subscriptions,
        private readonly ImpersonationContextInterface $impersonation,
    ) {}

    public function destroy(Request $request, string $subscription): JsonResponse|Response
    {
        if ($this->impersonation->isActive()) {
            return $this->impersonating();
        }

        abort_unless($this->subscriptions->forgetForUser($this->userId($request), $subscription), 404);

        if ($request->session()->get(WebPushSubscriptionServiceInterface::SESSION_KEY) === $subscription) {
            $request->session()->forget(WebPushSubscriptionServiceInterface::SESSION_KEY);
        }

        return response()->noContent();
    }

    /**
     * Sent at once, so the page can say whether it arrived at the push service: a test waits for no
     * queue. The browser of this session only, the one the page asked from.
     */
    public function test(Request $request, WebPushSenderInterface $sender): JsonResponse|Response
    {
        if ($this->impersonation->isActive()) {
            return $this->impersonating();
        }

        $id = $request->session()->get(WebPushSubscriptionServiceInterface::SESSION_KEY);
        $subscription = is_string($id) ? $this->subscriptions->find($id) : null;
        abort_unless($subscription instanceof WebPushSubscriptionDTO && $subscription->userId === $this->userId($request), 404);

        return match ($sender->send($subscription, self::TEST_PAYLOAD)) {
            WebPushDeliveryOutcome::Delivered => $this->delivered($subscription),
            WebPushDeliveryOutcome::Gone, WebPushDeliveryOutcome::Unusable => $this->gone($request, $subscription),
            WebPushDeliveryOutcome::RetryLater, WebPushDeliveryOutcome::Failed => response()->json(['error' => 'unavailable', 'message' => __('web_push.test_unavailable')], 503),
        };
    }

    private function delivered(WebPushSubscriptionDTO $subscription): Response
    {
        $this->subscriptions->markUsed($subscription->id);

        return response()->noContent();
    }

    private function gone(Request $request, WebPushSubscriptionDTO $subscription): JsonResponse
    {
        $this->subscriptions->forget($subscription->id);
        $request->session()->forget(WebPushSubscriptionServiceInterface::SESSION_KEY);

        return response()->json(['error' => 'gone', 'message' => __('web_push.test_gone')], 410);
    }

    private function userId(Request $request): string
    {
        $user = $request->user();
        abort_unless($user instanceof UserModel, 403);

        return (string) $user->id;
    }

    private function impersonating(): JsonResponse
    {
        return response()->json(['error' => 'impersonating', 'message' => __('web_push.impersonating')], 403);
    }
}
