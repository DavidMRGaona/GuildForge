<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final readonly class BlockBotsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('bot-protection.enabled', true)) {
            return $next($request);
        }

        $userAgent = $request->userAgent() ?? '';

        if ($userAgent === '') {
            return $next($request);
        }

        if ($this->isAllowedBot($userAgent)) {
            return $next($request);
        }

        if ($this->isBlockedBot($userAgent)) {
            $this->logBlockedRequest($request, $userAgent);

            return $this->blockedResponse($request);
        }

        return $next($request);
    }

    private function isAllowedBot(string $userAgent): bool
    {
        /** @var array<int, string> $allowedBots */
        $allowedBots = config('bot-protection.allowed_bots', []);

        return array_any($allowedBots, fn($pattern) => stripos($userAgent, $pattern) !== false);
    }

    private function isBlockedBot(string $userAgent): bool
    {
        /** @var array<int, string> $blockedBots */
        $blockedBots = config('bot-protection.blocked_bots', []);

        return array_any($blockedBots, fn($pattern) => stripos($userAgent, $pattern) !== false);
    }

    private function logBlockedRequest(Request $request, string $userAgent): void
    {
        if (! config('bot-protection.log_blocks', false)) {
            return;
        }

        Log::info('Blocked bot access', [
            'user_agent' => $userAgent,
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
        ]);
    }

    private function blockedResponse(Request $request): Response
    {
        $message = 'Access denied';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return response($message, 403);
    }
}
