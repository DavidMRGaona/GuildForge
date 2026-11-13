<?php

declare(strict_types=1);

namespace App\Application\Realtime\Services;

/**
 * Answers a browser that wants to subscribe to a private channel (POST /broadcasting/auth).
 */
interface RealtimeSubscriptionAuthorizerInterface
{
    /**
     * The signed `auth` value the WebSocket server accepts for this socket and channel, or null
     * when the person may not listen (or anything about the request is not as expected).
     *
     * @param  string  $channelName  as the browser sends it, with `private-`
     */
    public function authorize(?object $user, string $socketId, string $channelName): ?string;
}
