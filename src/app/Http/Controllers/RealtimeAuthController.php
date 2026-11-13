<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Realtime\Services\RealtimeConfigurationInterface;
use App\Application\Realtime\Services\RealtimeSubscriptionAuthorizerInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /broadcasting/auth: a browser asks to listen to a private channel (core#493). Without real
 * time configured the endpoint does not exist; anything not granted is a 403.
 */
final readonly class RealtimeAuthController
{
    public function __construct(
        private RealtimeConfigurationInterface $configuration,
        private RealtimeSubscriptionAuthorizerInterface $authorizer,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->configuration->enabled(), 404);

        $socketId = $request->input('socket_id');
        $channel = $request->input('channel_name');

        $auth = is_string($socketId) && is_string($channel)
            ? $this->authorizer->authorize($request->user(), $socketId, $channel)
            : null;

        abort_if($auth === null, 403);

        return new JsonResponse(['auth' => $auth]);
    }
}
