<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebPush;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\WebPush\StoreWebPushSubscriptionRequest;
use App\Http\Requests\WebPush\WebPushEndpointRequest;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * This browser starts or stops receiving the account's push messages (core#803). The session
 * remembers the subscription it made, so signing out forgets it even without the page's request.
 * Refused while impersonating: nobody subscribes a browser for someone else.
 */
final class WebPushSubscriptionController extends Controller
{
    public function __construct(
        private readonly WebPushSubscriptionServiceInterface $subscriptions,
        private readonly ImpersonationContextInterface $impersonation,
    ) {}

    public function store(StoreWebPushSubscriptionRequest $request): JsonResponse
    {
        if ($this->impersonation->isActive()) {
            return $this->impersonating();
        }

        $id = $this->subscriptions->subscribe($this->userId($request), $request->toDto());
        $request->session()->put(WebPushSubscriptionServiceInterface::SESSION_KEY, $id);

        return response()->json(['id' => $id], 201);
    }

    /**
     * The page tells the site, once per session, about the subscription its browser already has.
     * Only the account's own: anything else answers 404 and the page drops it from the browser.
     */
    public function refresh(WebPushEndpointRequest $request): JsonResponse
    {
        if ($this->impersonation->isActive()) {
            return $this->impersonating();
        }

        $id = $this->subscriptions->refresh($this->userId($request), $request->string('endpoint')->toString());
        abort_if($id === null, 404);
        $request->session()->put(WebPushSubscriptionServiceInterface::SESSION_KEY, $id);

        return response()->json(['id' => $id]);
    }

    public function destroy(WebPushEndpointRequest $request): JsonResponse|Response
    {
        if ($this->impersonation->isActive()) {
            return $this->impersonating();
        }

        abort_unless($this->subscriptions->unsubscribe($this->userId($request), $request->string('endpoint')->toString()), 404);
        $request->session()->forget(WebPushSubscriptionServiceInterface::SESSION_KEY);

        return response()->noContent();
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
