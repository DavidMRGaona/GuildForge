<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Realtime\Services\RealtimeConfigurationInterface;
use App\Application\Security\Services\ContentSecurityPolicySourcesInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SecurityHeadersMiddleware
{
    public function __construct(
        private ContentSecurityPolicySourcesInterface $sources,
        private RealtimeConfigurationInterface $realtime,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
        $response->headers->set('Content-Security-Policy', $this->buildCsp());

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function buildCsp(): string
    {
        $directives = [
            "default-src 'self'",
            $this->scriptSrc(),
            $this->styleSrc(),
            $this->imgSrc(),
            // The site serves its own fonts (resources/css/fonts.css, core#557)
            "font-src 'self' data:",
            $this->connectSrc(),
            $this->frameSrc(),
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Livewire and Alpine.js (Filament) require unsafe-inline and unsafe-eval.
     */
    private function scriptSrc(): string
    {
        $sources = "'self' 'unsafe-inline' 'unsafe-eval'";

        if (app()->environment('local')) {
            $sources .= ' http://localhost:5173';
        }

        return "script-src {$sources}";
    }

    /**
     * Tailwind and Filament use inline styles.
     */
    private function styleSrc(): string
    {
        $sources = "'self' 'unsafe-inline'";

        if (app()->environment('local')) {
            $sources .= ' http://localhost:5173';
        }

        return "style-src {$sources}";
    }

    private function imgSrc(): string
    {
        $sources = "'self' https://res.cloudinary.com https://*.tile.openstreetmap.org data:";

        if (app()->environment('local')) {
            $sources .= ' http://localhost:5173';
        }

        return "img-src {$sources}";
    }

    /**
     * Frames from the site itself, and from the exact https origins modules registered
     * (ContentSecurityPolicySourcesInterface): the players of content the reader chose to load.
     */
    private function frameSrc(): string
    {
        return trim("frame-src 'self' ".implode(' ', $this->sources->frameOrigins()));
    }

    /**
     * Requests to the site itself and, only while real time is on (core#493), the configured
     * WebSocket origin (`wss://host[:port]`, validated by the configuration).
     */
    private function connectSrc(): string
    {
        $sources = "'self'";

        if (app()->environment('local')) {
            $sources .= ' http://localhost:5173 ws://localhost:5173';
        }

        $websocket = $this->realtime->connectSource();
        if ($websocket !== null) {
            $sources .= ' '.$websocket;
        }

        return "connect-src {$sources}";
    }
}
