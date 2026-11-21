<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\WebPush\Services\WebPushConfigurationInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The push routes do not exist while push is off (core#803): 404, before any validation.
 */
final readonly class EnsureWebPushIsEnabled
{
    public function __construct(
        private WebPushConfigurationInterface $configuration,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->configuration->enabled(), 404);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
